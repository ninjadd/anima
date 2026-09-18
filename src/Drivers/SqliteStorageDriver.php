<?php

namespace Anima\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\SQLiteConnection;
use PDO;

class SqliteStorageDriver extends DatabaseStorageDriver
{
    /**
     * Database/table combinations whose schema has already been created in
     * this process, keyed by "{$databasePath}:{$table}". Keying by identity
     * (rather than a single shared flag) matters because a process can
     * legitimately open more than one SQLite database during its lifetime,
     * e.g. in tests or multi-tenant setups.
     *
     * @var array<string, true>
     */
    protected static array $initializedSchemas = [];

    /**
     * Create a new SQLite storage driver instance.
     */
    public function __construct(
        protected string $databasePath,
        string $table = 'anima_entries'
    ) {
        $this->assertValidTableName($table);

        $this->databasePath = $this->resolveDatabasePath($this->databasePath);

        $connection = $this->resolveSqliteConnection($this->databasePath);
        parent::__construct($connection, $table);

        $schemaKey = "{$this->databasePath}:{$table}";
        if (! isset(self::$initializedSchemas[$schemaKey])) {
            $this->ensureTableExists();
            self::$initializedSchemas[$schemaKey] = true;
        }
    }

    /**
     * Resolve a relative database path against the application's base path.
     * A relative path resolves against getcwd(), which differs between the
     * web server, CLI, and queue worker processes; anchoring it to base_path()
     * keeps it consistent across all of them.
     */
    protected function resolveDatabasePath(string $databasePath): string
    {
        if ($databasePath === ':memory:' || $this->isAbsolutePath($databasePath)) {
            return $databasePath;
        }

        return base_path($databasePath);
    }

    /**
     * Determine whether the given path is absolute, on Unix or Windows.
     */
    protected function isAbsolutePath(string $path): bool
    {
        return (bool) preg_match('#^(?:/|[A-Za-z]:[\\\\/]|\\\\\\\\)#', $path);
    }

    /**
     * Ensure the table name is a safe SQL identifier before it's interpolated
     * into raw SQL in ensureTableExists().
     *
     * @throws \InvalidArgumentException
     */
    protected function assertValidTableName(string $table): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException(
                "Invalid Anima SQLite table name [{$table}]. Table names must start with a letter or underscore and contain only letters, numbers, and underscores."
            );
        }
    }

    /**
     * Get the database path.
     */
    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }

    /**
     * Resolve a dedicated SQLite database connection.
     */
    protected function resolveSqliteConnection(string $databasePath): ConnectionInterface
    {
        if ($databasePath !== ':memory:') {
            $dir = dirname($databasePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (! file_exists($databasePath)) {
                touch($databasePath);
            }
        }

        $pdo = new PDO("sqlite:{$databasePath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // WAL lets readers proceed without blocking on a writer, and an explicit
        // busy_timeout bounds how long a write waits under contention instead of
        // relying on PDO_SQLITE's own 60-second default (a long silent hang is
        // worse for a webhook endpoint than a fast, predictable retry window).
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        return new SQLiteConnection($pdo, $databasePath, '', [
            'driver' => 'sqlite',
            'database' => $databasePath,
        ]);
    }

    /**
     * Ensure the payload table and indexes exist.
     */
    protected function ensureTableExists(): void
    {
        $this->connection->statement("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id VARCHAR(36) PRIMARY KEY,
                method VARCHAR(10) NOT NULL,
                uri TEXT NOT NULL,
                headers TEXT NOT NULL,
                payload LONGTEXT NULL,
                response_status INTEGER NULL,
                response_body LONGTEXT NULL,
                duration_ms REAL NULL,
                is_synthetic INTEGER NOT NULL DEFAULT 0,
                tags TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");

        $this->connection->statement("
            CREATE INDEX IF NOT EXISTS idx_{$this->table}_created_method ON {$this->table} (created_at, method)
        ");
    }
}
