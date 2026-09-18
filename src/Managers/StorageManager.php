<?php

namespace Anima\Managers;

use Anima\Contracts\PayloadStorageInterface;
use Anima\Drivers\DatabaseStorageDriver;
use Anima\Drivers\RedisStorageDriver;
use Anima\Drivers\SqliteStorageDriver;
use Illuminate\Support\Manager;

class StorageManager extends Manager implements PayloadStorageInterface
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('anima.storage.driver', 'database');
    }

    /**
     * Create the Database driver instance.
     */
    public function createDatabaseDriver(): DatabaseStorageDriver
    {
        $connectionName = $this->config->get('anima.storage.database.connection');
        $connection = $this->container->make('db')->connection($connectionName);
        $table = $this->config->get('anima.storage.database.table', 'anima_entries');

        return new DatabaseStorageDriver($connection, $table);
    }

    /**
     * Create the SQLite driver instance.
     */
    public function createSqliteDriver(): SqliteStorageDriver
    {
        $databasePath = $this->config->get('anima.storage.sqlite.database', storage_path('anima/anima.sqlite'));
        $table = $this->config->get('anima.storage.sqlite.table', 'anima_entries');

        return new SqliteStorageDriver($databasePath, $table);
    }

    /**
     * Create the Redis driver instance.
     */
    public function createRedisDriver(): RedisStorageDriver
    {
        $connectionName = $this->config->get('anima.storage.redis.connection', 'default');
        $redis = $this->container->make('redis')->connection($connectionName);
        $prefix = $this->config->get('anima.storage.redis.prefix', 'anima:entries');
        $ttl = $this->config->get('anima.storage.redis.ttl', 86400);
        $maxFilterScan = $this->config->get('anima.storage.redis.max_filter_scan', 5000);

        return new RedisStorageDriver($redis, $prefix, $ttl, $maxFilterScan);
    }

    /**
     * Store a payload record and return its unique identifier (UUID).
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function store(array $data): string
    {
        return $this->driver()->store($data);
    }

    /**
     * Find a payload record by its unique identifier.
     *
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->driver()->find($id);
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
        return $this->driver()->paginate($perPage, $filters);
    }

    /**
     * Delete a payload record by its unique identifier.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool
    {
        return $this->driver()->delete($id);
    }

    /**
     * Purge all stored payloads.
     *
     * @return bool
     */
    public function purge(): bool
    {
        return $this->driver()->purge();
    }
}
