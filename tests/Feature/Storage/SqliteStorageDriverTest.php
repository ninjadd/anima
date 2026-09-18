<?php

namespace Anima\Tests\Feature\Storage;

use Anima\Drivers\SqliteStorageDriver;
use Anima\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class SqliteStorageDriverTest extends TestCase
{
    protected string $tempDbDir;
    protected string $tempDbPath;
    protected SqliteStorageDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDbDir = sys_get_temp_dir() . '/anima_test_' . uniqid();
        $this->tempDbPath = $this->tempDbDir . '/isolated_anima.sqlite';
        $this->driver = new SqliteStorageDriver($this->tempDbPath);
    }

    protected function tearDown(): void
    {
        // WAL mode leaves -wal/-shm companion files next to the database file.
        foreach ([$this->tempDbPath, "{$this->tempDbPath}-wal", "{$this->tempDbPath}-shm"] as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        if (is_dir($this->tempDbDir)) {
            @rmdir($this->tempDbDir);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_directory_and_sqlite_file_and_schema_automatically(): void
    {
        $this->assertDirectoryExists($this->tempDbDir);
        $this->assertFileExists($this->tempDbPath);

        $id = $this->driver->store([
            'method' => 'PUT',
            'uri' => 'https://api.example.com/replay/123',
            'headers' => ['Authorization' => 'Bearer token123'],
            'payload' => ['customer_id' => 'cust_abc'],
            'response_status' => 202,
            'response_body' => ['accepted' => true],
            'duration_ms' => 88.4,
            'is_synthetic' => true,
            'tags' => ['shopify', 'replay'],
        ]);

        $this->assertIsString($id);
        $entry = $this->driver->find($id);

        $this->assertNotNull($entry);
        $this->assertSame($id, $entry['id']);
        $this->assertSame('PUT', $entry['method']);
        $this->assertSame('https://api.example.com/replay/123', $entry['uri']);
        $this->assertSame(['Authorization' => 'Bearer token123'], $entry['headers']);
        $this->assertSame(['customer_id' => 'cust_abc'], $entry['payload']);
        $this->assertIsInt($entry['response_status']);
        $this->assertSame(202, $entry['response_status']);
        $this->assertIsFloat($entry['duration_ms']);
        $this->assertSame(88.4, $entry['duration_ms']);
        $this->assertIsBool($entry['is_synthetic']);
        $this->assertTrue($entry['is_synthetic']);
        $this->assertSame(['shopify', 'replay'], $entry['tags']);
    }

    #[Test]
    public function it_remains_isolated_from_primary_connection(): void
    {
        // Primary connection has not run any migrations and table should not exist there
        $this->assertFalse(Schema::hasTable('anima_entries'));

        // Store into isolated SQLite driver
        $id = $this->driver->store([
            'method' => 'POST',
            'uri' => 'https://api.example.com/isolated',
            'payload' => ['isolated' => true],
        ]);

        // Exists in isolated driver
        $this->assertNotNull($this->driver->find($id));

        // Primary connection still does not have the table
        $this->assertFalse(Schema::connection(DB::getDefaultConnection())->hasTable('anima_entries'));
    }

    #[Test]
    public function it_can_paginate_filter_and_purge_isolated_sqlite(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->driver->store([
                'method' => $i % 2 === 0 ? 'POST' : 'GET',
                'uri' => "https://api.example.com/items/{$i}",
                'tags' => [$i % 2 === 0 ? 'even' : 'odd'],
            ]);
        }

        $all = $this->driver->paginate(10);
        $this->assertSame(4, $all['total']);

        $filtered = $this->driver->paginate(10, ['tag' => 'even']);
        $this->assertSame(2, $filtered['total']);

        $this->assertTrue($this->driver->purge());
        $this->assertSame(0, $this->driver->paginate()['total']);
    }

    #[Test]
    public function it_rejects_unsafe_table_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SqliteStorageDriver($this->tempDbPath . '.unsafe', 'entries; DROP TABLE users; --');
    }

    #[Test]
    public function it_accepts_a_normal_custom_table_name(): void
    {
        $path = $this->tempDbDir . '/custom_table.sqlite';
        $driver = new SqliteStorageDriver($path, 'my_custom_entries');

        $this->assertSame('my_custom_entries', $driver->getTable());

        foreach ([$path, "{$path}-wal", "{$path}-shm"] as $file) {
            @unlink($file);
        }
    }

    #[Test]
    public function it_configures_wal_mode_and_a_bounded_busy_timeout(): void
    {
        $connection = $this->driver->getConnection();

        $this->assertSame('wal', strtolower($connection->selectOne('PRAGMA journal_mode')->journal_mode));
        $this->assertSame(5000, (int) $connection->selectOne('PRAGMA busy_timeout')->timeout);
    }

    #[Test]
    public function it_creates_the_schema_independently_for_each_distinct_database_path(): void
    {
        // Guards against a single shared "already initialized" flag: a second
        // driver pointed at a different file must still get its own schema,
        // not silently skip creation because some other database already ran it.
        $otherPath = $this->tempDbDir . '/second_isolated_anima.sqlite';
        $other = new SqliteStorageDriver($otherPath);

        $id = $other->store(['method' => 'GET', 'uri' => 'https://api.example.com/second']);

        $this->assertNotNull($other->find($id));

        foreach ([$otherPath, "{$otherPath}-wal", "{$otherPath}-shm"] as $file) {
            @unlink($file);
        }
    }

    #[Test]
    public function it_resolves_a_relative_database_path_against_the_application_base_path(): void
    {
        $relative = 'anima-relative-test-' . uniqid() . '.sqlite';
        $driver = new SqliteStorageDriver($relative);

        $this->assertSame(base_path($relative), $driver->getDatabasePath());
        $this->assertFileExists(base_path($relative));

        foreach ([base_path($relative), base_path($relative) . '-wal', base_path($relative) . '-shm'] as $file) {
            @unlink($file);
        }
    }
}
