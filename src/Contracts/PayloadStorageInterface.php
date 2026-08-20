<?php

namespace Anima\Contracts;

interface PayloadStorageInterface
{
    /**
     * Store a payload record and return its unique identifier (UUID).
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function store(array $data): string;

    /**
     * Find a payload record by its unique identifier.
     *
     * @param string $id
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * Paginate stored payload records with optional filters.
     *
     * @param int $perPage
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function paginate(int $perPage = 25, array $filters = []): array;

    /**
     * Delete a payload record by its unique identifier.
     *
     * @param string $id
     * @return bool
     */
    public function delete(string $id): bool;

    /**
     * Purge all stored payloads.
     *
     * @return bool
     */
    public function purge(): bool;
}
