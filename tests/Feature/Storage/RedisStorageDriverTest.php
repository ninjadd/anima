<?php

namespace Anima\Tests\Feature\Storage;

use Anima\Drivers\RedisStorageDriver;
use Anima\Tests\FakeRedis;
use Anima\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CountingFakeRedis extends FakeRedis
{
    public int $getCalls = 0;

    public function get(string $key): ?string
    {
        $this->getCalls++;

        return parent::get($key);
    }
}

class RedisStorageDriverTest extends TestCase
{
    protected FakeRedis $redis;
    protected RedisStorageDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = new FakeRedis();
        $this->driver = new RedisStorageDriver($this->redis, 'anima:entries', 7200);
    }

    #[Test]
    public function it_stores_with_custom_prefix_ttl_and_strict_types(): void
    {
        $id = $this->driver->store([
            'method' => 'PATCH',
            'uri' => 'https://api.example.com/webhooks/github',
            'headers' => ['X-GitHub-Event' => 'push'],
            'payload' => ['ref' => 'refs/heads/main', 'commits' => [['id' => '123']]],
            'response_status' => 200,
            'response_body' => ['ok' => true],
            'duration_ms' => 45.2,
            'is_synthetic' => false,
            'tags' => ['github', 'push'],
        ]);

        $this->assertIsString($id);
        $this->assertSame(7200, $this->redis->ttls["anima:entries:{$id}"]);

        $entry = $this->driver->find($id);

        $this->assertNotNull($entry);
        $this->assertSame($id, $entry['id']);
        $this->assertSame('PATCH', $entry['method']);
        $this->assertSame('https://api.example.com/webhooks/github', $entry['uri']);
        $this->assertSame(['X-GitHub-Event' => 'push'], $entry['headers']);
        $this->assertIsArray($entry['payload']);
        $this->assertSame('refs/heads/main', $entry['payload']['ref']);
        $this->assertIsInt($entry['response_status']);
        $this->assertSame(200, $entry['response_status']);
        $this->assertIsFloat($entry['duration_ms']);
        $this->assertSame(45.2, $entry['duration_ms']);
        $this->assertIsBool($entry['is_synthetic']);
        $this->assertFalse($entry['is_synthetic']);
        $this->assertSame(['github', 'push'], $entry['tags']);
    }

    #[Test]
    public function it_filters_paginates_and_purges_in_redis(): void
    {
        $this->driver->store([
            'method' => 'POST',
            'uri' => 'https://api.example.com/hook/stripe',
            'tags' => ['stripe'],
            'response_status' => 200,
            'is_synthetic' => false,
            'created_at' => now()->subMinutes(10)->toDateTimeString(),
        ]);

        $this->driver->store([
            'method' => 'POST',
            'uri' => 'https://api.example.com/hook/github',
            'tags' => ['github'],
            'response_status' => 500,
            'is_synthetic' => true,
            'created_at' => now()->subMinutes(5)->toDateTimeString(),
        ]);

        $all = $this->driver->paginate(10);
        $this->assertSame(2, $all['total']);

        $filteredTag = $this->driver->paginate(10, ['tag' => 'stripe']);
        $this->assertSame(1, $filteredTag['total']);
        $this->assertSame('https://api.example.com/hook/stripe', $filteredTag['data'][0]['uri']);

        $syntheticOnly = $this->driver->paginate(10, ['is_synthetic' => true]);
        $this->assertSame(1, $syntheticOnly['total']);
        $this->assertSame('https://api.example.com/hook/github', $syntheticOnly['data'][0]['uri']);

        $this->assertTrue($this->driver->purge());
        $this->assertSame(0, $this->driver->paginate()['total']);
    }

    #[Test]
    public function it_reports_false_when_deleting_a_nonexistent_id(): void
    {
        $this->assertFalse($this->driver->delete('does-not-exist'));

        $id = $this->driver->store(['uri' => 'https://api.example.com/hook/exists']);
        $this->assertTrue($this->driver->delete($id));
        $this->assertFalse($this->driver->delete($id));
    }

    #[Test]
    public function filtered_paginate_batches_lookups_instead_of_one_get_per_entry(): void
    {
        $redis = new CountingFakeRedis();
        $driver = new RedisStorageDriver($redis, 'anima:entries', null);

        for ($i = 0; $i < 50; $i++) {
            $driver->store(['uri' => "https://api.example.com/{$i}", 'method' => 'POST']);
        }

        $redis->getCalls = 0;
        $result = $driver->paginate(10, ['method' => 'POST']);

        $this->assertSame(50, $result['total']);
        $this->assertCount(10, $result['data']);
        $this->assertSame(0, $redis->getCalls, 'Filtered paginate() should batch lookups via mget(), not call get() per entry.');
    }

    #[Test]
    public function default_listing_does_not_report_an_inflated_total_for_entries_past_the_ttl_window(): void
    {
        $driver = new RedisStorageDriver($this->redis, 'anima:entries', 60);

        // Past the 60s TTL window - Redis would already have expired this
        // entry's payload key natively, but the sorted-set index is never
        // told about that on its own.
        $id1 = $driver->store(['uri' => 'https://api.example.com/1', 'created_at' => now()->subSeconds(90)->toDateTimeString()]);
        $driver->store(['uri' => 'https://api.example.com/2', 'created_at' => now()->subSeconds(10)->toDateTimeString()]);
        $driver->store(['uri' => 'https://api.example.com/3', 'created_at' => now()->toDateTimeString()]);

        // Simulate that expiry actually having happened.
        unset($this->redis->storage[$driver->getItemKey($id1)]);

        $result = $driver->paginate(1);

        $this->assertSame(2, $result['total']);
    }
}
