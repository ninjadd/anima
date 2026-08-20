<?php

namespace Anima\Tests;

use Anima\Contracts\PayloadStorageInterface;
use Anima\Drivers\DatabaseStorageDriver;
use Anima\Drivers\RedisStorageDriver;
use Anima\Drivers\SqliteStorageDriver;
use Anima\Managers\StorageManager;
use PHPUnit\Framework\Attributes\Test;

class StorageManagerTest extends TestCase
{
    #[Test]
    public function it_resolves_storage_manager_from_container(): void
    {
        $manager = $this->app->make(StorageManager::class);
        $this->assertInstanceOf(StorageManager::class, $manager);
    }

    #[Test]
    public function it_binds_payload_storage_interface_to_default_driver(): void
    {
        $storage = $this->app->make(PayloadStorageInterface::class);
        $this->assertInstanceOf(PayloadStorageInterface::class, $storage);
        $this->assertInstanceOf(DatabaseStorageDriver::class, $storage);
    }

    #[Test]
    public function it_creates_database_driver(): void
    {
        $manager = $this->app->make(StorageManager::class);
        $driver = $manager->driver('database');

        $this->assertInstanceOf(DatabaseStorageDriver::class, $driver);
    }

    #[Test]
    public function it_creates_sqlite_driver(): void
    {
        $tempPath = sys_get_temp_dir() . '/anima_manager_' . uniqid() . '.sqlite';
        config(['anima.storage.sqlite.database' => $tempPath]);

        $manager = $this->app->make(StorageManager::class);
        $driver = $manager->driver('sqlite');

        $this->assertInstanceOf(SqliteStorageDriver::class, $driver);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    #[Test]
    public function it_creates_redis_driver_when_requested(): void
    {
        // Mock redis manager in container
        $redisMock = new FakeRedis();
        $this->app->instance('redis', new class($redisMock) {
            public function __construct(protected mixed $connection) {}
            public function connection(?string $name = null) {
                return $this->connection;
            }
        });

        $manager = $this->app->make(StorageManager::class);
        $driver = $manager->driver('redis');

        $this->assertInstanceOf(RedisStorageDriver::class, $driver);
    }

    #[Test]
    public function it_forwards_calls_through_storage_manager(): void
    {
        $tempPath = sys_get_temp_dir() . '/anima_forward_' . uniqid() . '.sqlite';
        config([
            'anima.storage.driver' => 'sqlite',
            'anima.storage.sqlite.database' => $tempPath,
        ]);

        $manager = new StorageManager($this->app);
        $id = $manager->store([
            'method' => 'POST',
            'url' => 'https://example.com/test',
            'payload' => ['hello' => 'world'],
        ]);

        $this->assertNotEmpty($id);
        $found = $manager->find($id);
        $this->assertSame('POST', $found['method']);

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }
}
