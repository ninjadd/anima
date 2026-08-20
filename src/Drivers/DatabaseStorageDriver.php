<?php

namespace Anima\Drivers;

use Anima\Contracts\PayloadStorageInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

class DatabaseStorageDriver implements PayloadStorageInterface
{
    /**
     * Create a new database storage driver instance.
     */
    public function __construct(
        protected ConnectionInterface $connection,
        protected string $table = 'anima_entries'
    ) {}

    /**
     * Get the database connection.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the table name.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get a query builder instance for the table.
     */
    protected function query()
    {
        return $this->connection->table($this->table);
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

        $headers = isset($data['headers'])
            ? (is_string($data['headers']) ? $data['headers'] : json_encode($data['headers']))
            : json_encode([]);

        $payload = isset($data['payload'])
            ? (is_string($data['payload']) ? $data['payload'] : json_encode($data['payload']))
            : (isset($data['body']) ? (is_string($data['body']) ? $data['body'] : json_encode($data['body'])) : null);

        $responseBody = isset($data['response_body'])
            ? (is_string($data['response_body']) ? $data['response_body'] : json_encode($data['response_body']))
            : null;

        $tags = null;
        if (isset($data['tags'])) {
            $tags = is_string($data['tags']) ? $data['tags'] : json_encode(is_array($data['tags']) ? array_values($data['tags']) : [$data['tags']]);
        } elseif (isset($data['tag'])) {
            $tags = json_encode([$data['tag']]);
        }

        $createdAt = $data['created_at'] ?? now()->toDateTimeString();
        $updatedAt = $data['updated_at'] ?? now()->toDateTimeString();

        $uri = $data['uri'] ?? ($data['url'] ?? '/');

        $record = [
            'id' => $id,
            'method' => isset($data['method']) ? strtoupper($data['method']) : 'POST',
            'uri' => $uri,
            'headers' => $headers,
            'payload' => $payload,
            'response_status' => isset($data['response_status']) ? (int) $data['response_status'] : (isset($data['status']) ? (int) $data['status'] : null),
            'response_body' => $responseBody,
            'duration_ms' => isset($data['duration_ms']) ? (float) $data['duration_ms'] : null,
            'is_synthetic' => (bool) ($data['is_synthetic'] ?? false),
            'tags' => $tags,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        $this->query()->insert($record);

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
        $row = $this->query()->where('id', $id)->first();

        if (! $row) {
            return null;
        }

        return $this->formatRow((array) $row);
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
        $query = $this->query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('uri', 'like', "%{$search}%")
                  ->orWhere('payload', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['method'])) {
            $query->where('method', strtoupper($filters['method']));
        }

        if (! empty($filters['tag'])) {
            $tag = $filters['tag'];
            $query->where(function ($q) use ($tag) {
                $q->where('tags', 'like', '%"' . $tag . '"%');
            });
        } elseif (! empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhere('tags', 'like', '%"' . $tag . '"%');
                }
            });
        }

        if (isset($filters['response_status'])) {
            $query->where('response_status', (int) $filters['response_status']);
        } elseif (isset($filters['status'])) {
            $query->where('response_status', (int) $filters['status']);
        }

        if (isset($filters['is_synthetic'])) {
            $query->where('is_synthetic', (bool) $filters['is_synthetic']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $page = (int) ($filters['page'] ?? 1);
        $page = max(1, $page);

        $total = (clone $query)->count();

        $rows = $query->orderBy('created_at', 'desc')
            ->forPage($page, $perPage)
            ->get();

        $data = array_map(fn ($row) => $this->formatRow((array) $row), $rows->all());
        $lastPage = (int) ceil($total / max(1, $perPage));

        return [
            'data' => $data,
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
        return $this->query()->where('id', $id)->delete() > 0;
    }

    /**
     * Purge all stored payloads.
     *
     * @return bool
     */
    public function purge(): bool
    {
        try {
            $this->query()->delete();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Format a database row into a strictly typed array with decoded JSON fields.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function formatRow(array $row): array
    {
        $headers = [];
        if (isset($row['headers'])) {
            if (is_array($row['headers'])) {
                $headers = $row['headers'];
            } elseif (is_string($row['headers'])) {
                $decoded = json_decode($row['headers'], true);
                $headers = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            }
        }

        $payload = null;
        if (isset($row['payload'])) {
            if (is_array($row['payload'])) {
                $payload = $row['payload'];
            } elseif (is_string($row['payload'])) {
                $decoded = json_decode($row['payload'], true);
                $payload = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['payload'];
            }
        }

        $responseBody = null;
        if (isset($row['response_body'])) {
            if (is_array($row['response_body'])) {
                $responseBody = $row['response_body'];
            } elseif (is_string($row['response_body'])) {
                $decoded = json_decode($row['response_body'], true);
                $responseBody = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['response_body'];
            }
        }

        $tags = null;
        if (isset($row['tags'])) {
            if (is_array($row['tags'])) {
                $tags = $row['tags'];
            } elseif (is_string($row['tags'])) {
                $decoded = json_decode($row['tags'], true);
                $tags = json_last_error() === JSON_ERROR_NONE ? $decoded : [$row['tags']];
            }
        }

        return [
            'id' => (string) $row['id'],
            'method' => (string) ($row['method'] ?? 'POST'),
            'uri' => (string) ($row['uri'] ?? ($row['url'] ?? '')),
            'headers' => $headers,
            'payload' => $payload,
            'response_status' => isset($row['response_status']) ? (int) $row['response_status'] : null,
            'response_body' => $responseBody,
            'duration_ms' => isset($row['duration_ms']) ? (float) $row['duration_ms'] : null,
            'is_synthetic' => (bool) ($row['is_synthetic'] ?? false),
            'tags' => $tags,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
