<?php

namespace Escalated\Plugins\ImportIntercom;

use Escalated\Laravel\Contracts\ImportAdapter;
use Escalated\Laravel\Models\ImportSourceMap;
use Escalated\Laravel\Support\ExtractResult;

class IntercomImportAdapter implements ImportAdapter
{
    private array $collectedAttachments = [];
    private ?string $currentJobId = null;

    /** Set by the framework before calling extract() — needed for reply iteration */
    public function setJobId(string $jobId): void
    {
        $this->currentJobId = $jobId;
    }

    public function name(): string
    {
        return 'intercom';
    }

    public function displayName(): string
    {
        return 'Intercom';
    }

    public function credentialFields(): array
    {
        return [
            [
                'name' => 'token',
                'label' => 'Access Token',
                'type' => 'password',
                'help' => 'Generate in Intercom Developer Hub under Your Apps > Authentication',
            ],
        ];
    }

    public function testConnection(array $credentials): bool
    {
        return IntercomClient::fromCredentials($credentials)->testConnection();
    }

    public function entityTypes(): array
    {
        return ['agents', 'tags', 'departments', 'contacts', 'tickets', 'replies', 'attachments'];
    }

    public function defaultFieldMappings(string $entityType): array
    {
        return match ($entityType) {
            'tickets' => [
                'title' => 'title',
                'state' => 'status',
                'priority' => 'priority',
                'assignee' => 'assigned_to',
                'contacts' => 'requester',
                'team_assignee_id' => 'department',
                'tags' => 'tags',
            ],
            default => [],
        };
    }

    public function availableSourceFields(string $entityType, array $credentials): array
    {
        return match ($entityType) {
            'tickets' => [
                ['name' => 'title', 'label' => 'Title (or first message excerpt)', 'escalated_options' => ['title']],
                ['name' => 'state', 'label' => 'State', 'escalated_options' => ['status']],
                ['name' => 'priority', 'label' => 'Priority', 'escalated_options' => ['priority']],
                ['name' => 'assignee', 'label' => 'Assignee (Admin)', 'escalated_options' => ['assigned_to']],
                ['name' => 'contacts', 'label' => 'Contact (Requester)', 'escalated_options' => ['requester']],
                ['name' => 'team_assignee_id', 'label' => 'Team', 'escalated_options' => ['department']],
                ['name' => 'tags', 'label' => 'Tags', 'escalated_options' => ['tags']],
            ],
            default => [],
        };
    }

    public function extract(string $entityType, array $credentials, ?string $cursor): ExtractResult
    {
        $client = IntercomClient::fromCredentials($credentials);

        return match ($entityType) {
            'agents' => $this->extractAgents($client, $cursor),
            'tags' => $this->extractTags($client, $cursor),
            'departments' => $this->extractDepartments($client, $cursor),
            'contacts' => $this->extractContacts($client, $cursor),
            'tickets' => $this->extractTickets($client, $cursor),
            'replies' => $this->extractReplies($client, $cursor),
            'attachments' => $this->extractAttachments($client, $cursor),
            default => new ExtractResult([], null),
        };
    }

    /**
     * Extract all admins from GET /admins.
     * Intercom returns all admins in a single response (no pagination).
     */
    private function extractAgents(IntercomClient $client, ?string $cursor): ExtractResult
    {
        if ($cursor !== null) {
            return new ExtractResult([], null); // Single page only
        }

        $data = $client->get('admins');

        $records = array_map(
            [IntercomFieldMapper::class, 'normalizeAdmin'],
            $data['admins'] ?? [],
        );

        return new ExtractResult($records, null, count($records));
    }

    /**
     * Extract all tags from GET /tags.
     * Intercom returns all tags in a single response (no pagination).
     */
    private function extractTags(IntercomClient $client, ?string $cursor): ExtractResult
    {
        if ($cursor !== null) {
            return new ExtractResult([], null); // Single page only
        }

        $data = $client->get('tags');

        $records = array_map(
            fn ($tag) => ['source_id' => (string) $tag['id'], 'name' => $tag['name'] ?? ''],
            $data['data'] ?? [],
        );

        return new ExtractResult($records, null, count($records));
    }

    /**
     * Extract all teams from GET /teams, mapped to departments.
     * Intercom returns all teams in a single response (no pagination).
     */
    private function extractDepartments(IntercomClient $client, ?string $cursor): ExtractResult
    {
        if ($cursor !== null) {
            return new ExtractResult([], null); // Single page only
        }

        $data = $client->get('teams');

        $records = array_map(
            [IntercomFieldMapper::class, 'normalizeTeam'],
            $data['teams'] ?? [],
        );

        return new ExtractResult($records, null, count($records));
    }

    /**
     * Extract contacts using POST /contacts/search with cursor-based pagination.
     * Contacts API v2 unifies leads and users into a single resource.
     *
     * Cursor format: "starting_after:<cursor_value>" or null to start from the beginning.
     */
    private function extractContacts(IntercomClient $client, ?string $cursor): ExtractResult
    {
        $body = [
            'query' => [
                'operator' => 'OR',
                'value' => [
                    ['field' => 'role', 'operator' => '=', 'value' => 'user'],
                    ['field' => 'role', 'operator' => '=', 'value' => 'lead'],
                ],
            ],
        ];

        if ($cursor !== null && str_starts_with($cursor, 'starting_after:')) {
            $body['pagination'] = [
                'per_page' => 150,
                'starting_after' => substr($cursor, 15),
            ];
        } else {
            $body['pagination'] = ['per_page' => 150];
        }

        $data = $client->search('contacts/search', $body);

        $records = array_map(
            [IntercomFieldMapper::class, 'normalizeContact'],
            $data['data'] ?? [],
        );

        $nextStartingAfter = $data['pages']['next']['starting_after'] ?? null;
        $nextCursor = $nextStartingAfter !== null ? "starting_after:{$nextStartingAfter}" : null;

        return new ExtractResult($records, $nextCursor, $data['total_count'] ?? null);
    }

    /**
     * Extract conversations from GET /conversations with cursor-based pagination.
     *
     * Cursor format: "starting_after:<cursor_value>" or null to start.
     */
    private function extractTickets(IntercomClient $client, ?string $cursor): ExtractResult
    {
        $query = ['per_page' => 150];

        if ($cursor !== null && str_starts_with($cursor, 'starting_after:')) {
            $query['starting_after'] = substr($cursor, 15);
        }

        $data = $client->get('conversations', $query);

        $records = array_map(
            [IntercomFieldMapper::class, 'normalizeConversation'],
            $data['conversations'] ?? [],
        );

        $nextStartingAfter = $data['pages']['next']['starting_after'] ?? null;
        $nextCursor = $nextStartingAfter !== null ? "starting_after:{$nextStartingAfter}" : null;

        return new ExtractResult($records, $nextCursor, $data['total_count'] ?? null);
    }

    /**
     * Extract replies by iterating through all imported conversations and fetching their parts.
     *
     * Cursor format:
     *   - null / "idx:N"  — move to the Nth ticket in the source map and start fetching its parts
     *   - "tid:CONV_ID|starting_after:CURSOR" — paginating within a single conversation's parts
     */
    private function extractReplies(IntercomClient $client, ?string $cursor): ExtractResult
    {
        if ($cursor !== null && str_starts_with($cursor, 'tid:')) {
            // Paginating within a conversation's parts
            $rest = substr($cursor, 4);
            $pipePos = strrpos($rest, '|');
            $convId = substr($rest, 0, $pipePos);
            $pageRef = substr($rest, $pipePos + 1); // "starting_after:CURSOR"

            $startingAfter = str_starts_with($pageRef, 'starting_after:')
                ? substr($pageRef, 15)
                : null;

            $data = $client->get(
                "conversations/{$convId}/parts",
                $startingAfter !== null ? ['starting_after' => $startingAfter] : [],
            );

            $records = $this->normalizeConversationParts($data, $convId);

            $nextStartingAfter = $data['pages']['next']['starting_after'] ?? null;
            $nextCursor = $nextStartingAfter !== null
                ? "tid:{$convId}|starting_after:{$nextStartingAfter}"
                : null; // Done with this conversation — caller re-enters with next idx

            return new ExtractResult($records, $nextCursor);
        }

        // Advance to the next conversation
        $offset = 0;
        if ($cursor !== null && str_starts_with($cursor, 'idx:')) {
            $offset = (int) substr($cursor, 4);
        }

        $ticketMap = ImportSourceMap::where('import_job_id', $this->currentJobId ?? '')
            ->where('entity_type', 'tickets')
            ->orderBy('id')
            ->offset($offset)
            ->first();

        if (! $ticketMap) {
            return new ExtractResult([], null); // All conversations processed
        }

        $convId = $ticketMap->source_id;

        $data = $client->get("conversations/{$convId}/parts");
        $records = $this->normalizeConversationParts($data, $convId);

        $nextStartingAfter = $data['pages']['next']['starting_after'] ?? null;

        if ($nextStartingAfter !== null) {
            // More pages of parts for this conversation
            $nextCursor = "tid:{$convId}|starting_after:{$nextStartingAfter}";
        } else {
            // Move to next conversation
            $nextCursor = 'idx:' . ($offset + 1);
        }

        return new ExtractResult($records, $nextCursor);
    }

    /**
     * Normalize conversation parts, collecting attachments as a side-effect.
     * Only imports content-bearing part types: comment and note.
     */
    private function normalizeConversationParts(array $data, string $convId): array
    {
        $records = [];
        $contentParts = ['comment', 'note'];

        foreach ($data['conversation_parts']['conversation_parts'] ?? [] as $part) {
            $partType = $part['part_type'] ?? '';

            if (! in_array($partType, $contentParts, true)) {
                continue;
            }

            $records[] = IntercomFieldMapper::normalizeConversationPart($part, $convId);

            // Collect attachments from this part
            foreach ($part['attachments'] ?? [] as $attachment) {
                $this->collectedAttachments[] = IntercomFieldMapper::normalizeAttachment(
                    $attachment,
                    (string) $part['id'],
                );
            }
        }

        return $records;
    }

    /**
     * Return all attachment metadata collected during reply extraction.
     * Actual file downloads are handled by the framework.
     *
     * Note: Intercom attachment URLs are temporary signed URLs. If a download
     * fails due to expiry, the framework should re-fetch the parent conversation
     * part to obtain a fresh URL before retrying.
     */
    private function extractAttachments(IntercomClient $client, ?string $cursor): ExtractResult
    {
        if ($cursor !== null) {
            return new ExtractResult([], null); // All returned in first call
        }

        $records = $this->collectedAttachments;
        $this->collectedAttachments = [];

        return new ExtractResult($records, null, count($records));
    }
}
