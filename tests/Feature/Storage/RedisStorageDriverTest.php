<?php

namespace Anima\Tests\Feature\Storage;

use Anima\Drivers\RedisStorageDriver;
use Anima\Tests\FakeRedis;
use Anima\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
}
