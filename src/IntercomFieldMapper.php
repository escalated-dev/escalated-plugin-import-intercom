<?php

namespace Escalated\Plugins\ImportIntercom;

class IntercomFieldMapper
{
    public static function stateMap(): array
    {
        return [
            'open' => 'open',
            'closed' => 'closed',
            'snoozed' => 'waiting_on_agent',
        ];
    }

    public static function priorityMap(): array
    {
        return [
            'not_priority' => 'medium',
            'priority' => 'high',
        ];
    }

    public static function mapState(?string $state): string
    {
        return static::stateMap()[$state ?? 'open'] ?? 'open';
    }

    public static function mapPriority(?string $priority): string
    {
        return static::priorityMap()[$priority ?? 'not_priority'] ?? 'medium';
    }

    /**
     * Normalize an Intercom conversation into the standard import format.
     *
     * Conversations may not have an explicit title. When absent, the first
     * source message body is truncated to produce a short excerpt.
     */
    public static function normalizeConversation(array $conv): array
    {
        $title = $conv['title'] ?? null;
        if (empty($title)) {
            $body = $conv['source']['body'] ?? '';
            // Strip HTML tags and collapse whitespace for the excerpt
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
            $title = $plain !== '' ? mb_substr($plain, 0, 100) : 'No subject';
        }

        $assigneeId = $conv['assignee']['id'] ?? null;
        // Intercom assignee can be an admin or a team; only map when type is admin
        $assigneeSourceId = ($conv['assignee']['type'] ?? '') === 'admin' ? (string) $assigneeId : null;
        $teamSourceId = ($conv['assignee']['type'] ?? '') === 'team' ? (string) $assigneeId : null;

        // Prefer the team from the conversation's team field if present
        if (isset($conv['team_assignee_id'])) {
            $teamSourceId = (string) $conv['team_assignee_id'];
        }

        $tagSourceIds = array_map(
            fn ($tag) => (string) $tag['id'],
            $conv['tags']['tags'] ?? [],
        );

        $contactSourceId = null;
        foreach ($conv['contacts']['contacts'] ?? [] as $contact) {
            // First contact is the requester
            $contactSourceId = (string) $contact['id'];
            break;
        }

        return [
            'source_id' => (string) $conv['id'],
            'title' => $title,
            'status' => static::mapState($conv['state'] ?? null),
            'priority' => static::mapPriority($conv['priority'] ?? null),
            'assignee_source_id' => $assigneeSourceId,
            'department_source_id' => $teamSourceId,
            'requester_source_id' => $contactSourceId,
            'tag_source_ids' => $tagSourceIds,
            'metadata' => [
                'intercom_id' => $conv['id'],
            ],
            'created_at' => isset($conv['created_at']) ? date('c', $conv['created_at']) : null,
            'updated_at' => isset($conv['updated_at']) ? date('c', $conv['updated_at']) : null,
        ];
    }

    /**
     * Normalize an Intercom contact (lead or user — unified in API v2).
     */
    public static function normalizeContact(array $contact): array
    {
        return [
            'source_id' => (string) $contact['id'],
            'name' => $contact['name'] ?? '',
            'email' => $contact['email'] ?? '',
            'role' => $contact['role'] ?? 'user',
            'metadata' => [
                'intercom_id' => $contact['id'],
                'external_id' => $contact['external_id'] ?? null,
            ],
            'created_at' => isset($contact['created_at']) ? date('c', $contact['created_at']) : null,
            'updated_at' => isset($contact['updated_at']) ? date('c', $contact['updated_at']) : null,
        ];
    }

    /**
     * Normalize an Intercom admin into the agent import format.
     */
    public static function normalizeAdmin(array $admin): array
    {
        return [
            'source_id' => (string) $admin['id'],
            'name' => $admin['name'] ?? '',
            'email' => $admin['email'] ?? '',
            'role' => 'agent',
        ];
    }

    /**
     * Normalize an Intercom team into the department import format.
     */
    public static function normalizeTeam(array $team): array
    {
        return [
            'source_id' => (string) $team['id'],
            'name' => $team['name'] ?? 'Unknown',
        ];
    }

    /**
     * Normalize a conversation part (reply, note, or assignment event) into a reply record.
     *
     * Intercom part types: comment, note, assignment, open, close, ...
     * We only import content-bearing parts (comment, note).
     */
    public static function normalizeConversationPart(array $part, string $conversationSourceId): array
    {
        $isNote = ($part['part_type'] ?? '') === 'note';
        $authorSourceId = null;
        $authorType = $part['author']['type'] ?? '';

        // Only map admin and bot authors to agent source IDs; contact authors stay as contact
        if ($authorType === 'admin' || $authorType === 'bot') {
            $authorSourceId = (string) ($part['author']['id'] ?? '');
        }

        $contactAuthorSourceId = null;
        if ($authorType === 'user' || $authorType === 'lead' || $authorType === 'contact') {
            $contactAuthorSourceId = (string) ($part['author']['id'] ?? '');
        }

        return [
            'source_id' => (string) $part['id'],
            'ticket_source_id' => $conversationSourceId,
            'body' => $part['body'] ?? '',
            'is_internal_note' => $isNote,
            'author_source_id' => $authorSourceId,
            'contact_author_source_id' => $contactAuthorSourceId,
            'created_at' => isset($part['created_at']) ? date('c', $part['created_at']) : null,
            'updated_at' => isset($part['updated_at']) ? date('c', $part['updated_at']) : null,
        ];
    }

    /**
     * Normalize an attachment from a conversation part.
     */
    public static function normalizeAttachment(array $attachment, string $parentSourceId): array
    {
        return [
            'source_id' => $attachment['url'] ?? (string) ($attachment['id'] ?? uniqid('att_')),
            'parent_type' => 'reply',
            'parent_source_id' => $parentSourceId,
            'filename' => $attachment['name'] ?? 'unknown',
            'mime_type' => $attachment['content_type'] ?? 'application/octet-stream',
            'size' => $attachment['filesize'] ?? 0,
            'download_url' => $attachment['url'] ?? '',
        ];
    }
}
