<?php

namespace Anima\Drivers;

use Anima\Contracts\PayloadStorageInterface;
use Illuminate\Support\Str;

class RedisStorageDriver implements PayloadStorageInterface
{
    /**
     * Create a new Redis storage driver instance.
     *
     * @param mixed $redis
     * @param string $prefix
     * @param int|null $ttl
     */
    public function __construct(
        protected mixed $redis,
        protected string $prefix = 'anima:entries',
        protected ?int $ttl = 86400
    ) {}

    /**
     * Get the Redis connection instance.
     */
    public function getRedis(): mixed
    {
        return $this->redis;
    }

    /**
     * Get the Redis key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get the TTL in seconds.
     */
    public function getTtl(): ?int
    {
        return $this->ttl;
    }

    /**
     * Get the sorted set index key.
     */
    public function getIndexKey(): string
    {
        return "{$this->prefix}:index";
    }

    /**
     * Get the key for a specific payload ID.
     */
    public function getItemKey(string $id): string
    {
        return "{$this->prefix}:{$id}";
    }

    /**
     * Store a payload record and return its unique identifier (UUID).
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function store(array $data): string
    {
        $id = (string) ($data['id'] ?? Str::uuid());

        $tags = null;
        if (isset($data['tags'])) {
            $tags = is_array($data['tags']) ? array_values($data['tags']) : (is_string($data['tags']) ? json_decode($data['tags'], true) ?? [$data['tags']] : [$data['tags']]);
        } elseif (isset($data['tag'])) {
            $tags = [$data['tag']];
        }

        $headers = [];
        if (isset($data['headers'])) {
            $headers = is_array($data['headers']) ? $data['headers'] : (is_string($data['headers']) ? json_decode($data['headers'], true) ?? [] : []);
        }

        $record = [
            'id' => $id,
            'method' => isset($data['method']) ? strtoupper($data['method']) : 'POST',
            'uri' => (string) ($data['uri'] ?? ($data['url'] ?? '/')),
            'headers' => $headers,
            'payload' => $data['payload'] ?? ($data['body'] ?? null),
            'response_status' => isset($data['response_status']) ? (int) $data['response_status'] : (isset($data['status']) ? (int) $data['status'] : null),
            'response_body' => $data['response_body'] ?? null,
            'duration_ms' => isset($data['duration_ms']) ? (float) $data['duration_ms'] : null,
            'is_synthetic' => (bool) ($data['is_synthetic'] ?? false),
            'tags' => $tags,
            'created_at' => (string) ($data['created_at'] ?? now()->toDateTimeString()),
            'updated_at' => (string) ($data['updated_at'] ?? now()->toDateTimeString()),
        ];

        $key = $this->getItemKey($id);
        $json = json_encode($record);

        if ($this->ttl && $this->ttl > 0) {
            $this->redis->setex($key, $this->ttl, $json);
        } else {
            $this->redis->set($key, $json);
        }

        $timestamp = (float) (strtotime($record['created_at']) ?: time());

        try {
            $this->redis->zadd($this->getIndexKey(), $timestamp, $id);
        } catch (\Throwable) {
            $this->redis->zadd($this->getIndexKey(), [$id => $timestamp]);
        }

        return $id;
    }

    /**
     * Find a payload record by its unique identifier.
     *
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $raw = $this->redis->get($this->getItemKey($id));

        if (! $raw) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return null;
        }

        return $this->formatRecord($data);
    }

    /**
     * Paginate stored payload records with optional filters.
     *
     * @param int $perPage
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function paginate(int $perPage = 25, array $filters = []): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $page = max(1, $page);

        $hasFilters = ! empty($filters['search'])
            || ! empty($filters['method'])
            || ! empty($filters['tag'])
            || ! empty($filters['tags'])
            || isset($filters['response_status'])
            || isset($filters['status'])
            || isset($filters['is_synthetic'])
            || ! empty($filters['from'])
            || ! empty($filters['to']);

        if ($hasFilters) {
            $allIds = $this->redis->zrevrange($this->getIndexKey(), 0, -1) ?: [];
            $filtered = [];

            foreach ($allIds as $id) {
                $record = $this->find((string) $id);
                if (! $record) {
                    $this->redis->zrem($this->getIndexKey(), $id);
                    continue;
                }

                if ($this->matchesFilters($record, $filters)) {
                    $filtered[] = $record;
                }
            }

            $total = count($filtered);
            $offset = ($page - 1) * $perPage;
            $items = array_slice($filtered, $offset, $perPage);
            $lastPage = (int) ceil($total / max(1, $perPage));

            return [
                'data' => array_values($items),
                'total' => (int) $total,
                'per_page' => (int) $perPage,
                'current_page' => $page,
                'last_page' => max(1, $lastPage),
            ];
        }

        $total = (int) ($this->redis->zcard($this->getIndexKey()) ?: 0);
        $start = ($page - 1) * $perPage;
        $stop = $start + $perPage - 1;

        $ids = $this->redis->zrevrange($this->getIndexKey(), $start, $stop) ?: [];
        $items = [];

        foreach ($ids as $id) {
            $record = $this->find((string) $id);
            if ($record) {
                $items[] = $record;
            } else {
                $this->redis->zrem($this->getIndexKey(), $id);
            }
        }

        $lastPage = (int) ceil($total / max(1, $perPage));

        return [
            'data' => $items,
            'total' => (int) $total,
            'per_page' => (int) $perPage,
            'current_page' => $page,
            'last_page' => max(1, $lastPage),
        ];
    }

    /**
     * Delete a payload record by its unique identifier.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        $this->redis->del($this->getItemKey($id));
        $this->redis->zrem($this->getIndexKey(), $id);

        return true;
    }

    /**
     * Purge all stored payloads.
     *
     * @return bool
     */
    public function purge(): bool
    {
        try {
            $allIds = $this->redis->zrevrange($this->getIndexKey(), 0, -1) ?: [];
            foreach ($allIds as $id) {
                $this->redis->del($this->getItemKey((string) $id));
                $this->redis->zrem($this->getIndexKey(), (string) $id);
            }
            $this->redis->del($this->getIndexKey());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Format record with strict types.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function formatRecord(array $data): array
    {
        return [
            'id' => (string) $data['id'],
            'method' => (string) ($data['method'] ?? 'POST'),
            'uri' => (string) ($data['uri'] ?? ($data['url'] ?? '')),
            'headers' => is_array($data['headers'] ?? null) ? $data['headers'] : [],
            'payload' => $data['payload'] ?? null,
            'response_status' => isset($data['response_status']) ? (int) $data['response_status'] : null,
            'response_body' => $data['response_body'] ?? null,
            'duration_ms' => isset($data['duration_ms']) ? (float) $data['duration_ms'] : null,
            'is_synthetic' => (bool) ($data['is_synthetic'] ?? false),
            'tags' => is_array($data['tags'] ?? null) ? $data['tags'] : null,
            'created_at' => (string) ($data['created_at'] ?? ''),
            'updated_at' => (string) ($data['updated_at'] ?? ''),
        ];
    }

    /**
     * Check if a record matches given filters.
     *
     * @param array<string, mixed> $record
     * @param array<string, mixed> $filters
     * @return bool
     */
    protected function matchesFilters(array $record, array $filters): bool
    {
        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $uri = strtolower($record['uri'] ?? '');
            $tagsStr = strtolower(is_array($record['tags'] ?? null) ? implode(' ', $record['tags']) : '');
            $payload = strtolower(is_array($record['payload'] ?? null) ? json_encode($record['payload']) : (string) ($record['payload'] ?? ''));

            if (! str_contains($uri, $search) && ! str_contains($tagsStr, $search) && ! str_contains($payload, $search)) {
                return false;
            }
        }

        if (! empty($filters['method']) && strtoupper($record['method'] ?? '') !== strtoupper($filters['method'])) {
            return false;
        }

        if (! empty($filters['tag'])) {
            $recordTags = $record['tags'] ?? [];
            if (! is_array($recordTags) || ! in_array($filters['tag'], $recordTags)) {
                return false;
            }
        } elseif (! empty($filters['tags'])) {
            $expectedTags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $recordTags = $record['tags'] ?? [];
            if (! is_array($recordTags) || empty(array_intersect($expectedTags, $recordTags))) {
                return false;
            }
        }

        $expectedStatus = $filters['response_status'] ?? ($filters['status'] ?? null);
        if ($expectedStatus !== null && ($record['response_status'] ?? null) !== (int) $expectedStatus) {
            return false;
        }

        if (isset($filters['is_synthetic']) && ($record['is_synthetic'] ?? false) !== (bool) $filters['is_synthetic']) {
            return false;
        }

        if (! empty($filters['from']) && ($record['created_at'] ?? '') < $filters['from']) {
            return false;
        }

        if (! empty($filters['to']) && ($record['created_at'] ?? '') > $filters['to']) {
            return false;
        }

        return true;
    }
}
