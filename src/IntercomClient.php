<?php

namespace Escalated\Plugins\ImportIntercom;

use Illuminate\Support\Facades\Http;

class IntercomClient
{
    private const BASE_URL = 'https://api.intercom.io/';

    /**
     * Intercom allows 1000 req/min. We distribute over 10-second windows,
     * allowing up to ~166 requests per window before we slow down.
     */
    private const RATE_WINDOW_SECONDS = 10;
    private const RATE_LIMIT_PER_WINDOW = 166;

    private string $token;
    private int $windowRequestCount = 0;
    private int $windowStartTime = 0;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->windowStartTime = time();
    }

    public static function fromCredentials(array $credentials): static
    {
        return new static($credentials['token']);
    }

    /**
     * Test the connection by calling /me.
     */
    public function testConnection(): bool
    {
        $response = $this->get('me');
        return isset($response['id']);
    }

    /**
     * Make an authenticated GET request.
     */
    public function get(string $endpoint, array $query = []): array
    {
        $url = str_starts_with($endpoint, 'http')
            ? $endpoint
            : self::BASE_URL . ltrim($endpoint, '/');

        return $this->request('GET', $url, $query);
    }

    /**
     * Make an authenticated POST request (used for search endpoints).
     */
    public function search(string $endpoint, array $body = []): array
    {
        $url = str_starts_with($endpoint, 'http')
            ? $endpoint
            : self::BASE_URL . ltrim($endpoint, '/');

        return $this->request('POST', $url, [], $body);
    }

    /**
     * Paginate a list endpoint following cursor-based pagination.
     *
     * Yields successive response arrays. The caller is responsible for
     * reading the relevant data key and the next cursor from each response.
     *
     * Intercom cursor pagination: next page URL lives at pages.next.starting_after.
     * Pass as ?starting_after=<cursor> query parameter.
     */
    public function paginate(string $endpoint, array $query = [], int $perPage = 150): \Generator
    {
        $query['per_page'] = $perPage;

        while (true) {
            $data = $this->get($endpoint, $query);
            yield $data;

            $nextCursor = $data['pages']['next']['starting_after'] ?? null;
            if ($nextCursor === null) {
                break;
            }

            $query['starting_after'] = $nextCursor;
        }
    }

    /**
     * Paginate a search endpoint (POST) following cursor-based pagination.
     */
    public function paginateSearch(string $endpoint, array $body = [], int $perPage = 150): \Generator
    {
        $body['pagination'] = ['per_page' => $perPage];

        while (true) {
            $data = $this->search($endpoint, $body);
            yield $data;

            $nextCursor = $data['pages']['next']['starting_after'] ?? null;
            if ($nextCursor === null) {
                break;
            }

            $body['pagination']['starting_after'] = $nextCursor;
        }
    }

    /**
     * Scroll through all companies using the /companies/scroll endpoint.
     * Intercom's scroll API uses a scroll_param token rather than cursor pagination.
     */
    public function scrollCompanies(): \Generator
    {
        // Initiate scroll
        $data = $this->get('companies/scroll');
        yield $data;

        $scrollParam = $data['scroll_param'] ?? null;

        while ($scrollParam !== null) {
            $data = $this->get('companies/scroll', ['scroll_param' => $scrollParam]);

            if (empty($data['data'])) {
                break;
            }

            yield $data;

            $scrollParam = $data['scroll_param'] ?? null;
        }
    }

    private function request(string $method, string $url, array $query = [], array $body = [], int $retries = 3): array
    {
        $this->enforceRateLimit();

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $http = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/json',
                'Intercom-Version' => '2.11',
            ])->timeout(30);

            $response = $method === 'POST'
                ? $http->post($url, $body)
                : $http->get($url, $query);

            $this->windowRequestCount++;

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After', 60);
                sleep(min($retryAfter, 120));
                // Reset window tracking after sleeping
                $this->windowRequestCount = 0;
                $this->windowStartTime = time();
                continue;
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            if ($response->status() >= 500 && $attempt < $retries) {
                sleep(2 ** $attempt);
                continue;
            }

            throw new \RuntimeException(
                "Intercom API error ({$response->status()}): " . $response->body()
            );
        }

        throw new \RuntimeException('Intercom API request failed after retries.');
    }

    /**
     * Distribute requests evenly across 10-second windows (166 req/window = ~1000 req/min).
     */
    private function enforceRateLimit(): void
    {
        $now = time();
        $elapsed = $now - $this->windowStartTime;

        if ($elapsed >= self::RATE_WINDOW_SECONDS) {
            // New window: reset counter
            $this->windowRequestCount = 0;
            $this->windowStartTime = $now;
            return;
        }

        if ($this->windowRequestCount >= self::RATE_LIMIT_PER_WINDOW) {
            // Pause until the current window expires
            $remaining = self::RATE_WINDOW_SECONDS - $elapsed;
            if ($remaining > 0) {
                sleep($remaining);
            }
            $this->windowRequestCount = 0;
            $this->windowStartTime = time();
        }
    }
}
