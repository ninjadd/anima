<?php

namespace Anima\Tests\Feature;

use Anima\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RateLimitingTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Config must be baked in before the service provider boots and
        // registers routes, since the throttle limit is read at route
        // registration time (not per-request).
        $app['config']->set('anima.rate_limits.purge', '2,1');
        $app['config']->set('anima.rate_limits.replay', '2,1');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 2) . '/database/migrations/create_anima_entries_table.php.stub';
        $migration->up();
    }

    #[Test]
    public function it_throttles_the_purge_endpoint(): void
    {
        $this->deleteJson('/anima/api/entries')->assertStatus(200);
        $this->deleteJson('/anima/api/entries')->assertStatus(200);
        $this->deleteJson('/anima/api/entries')->assertStatus(429);
    }

    #[Test]
    public function it_throttles_the_replay_endpoint(): void
    {
        $payload = [
            'uri' => '/anima/api/entries',
            'method' => 'GET',
        ];

        $this->postJson('/anima/api/replay', $payload)->assertStatus(422);
        $this->postJson('/anima/api/replay', $payload)->assertStatus(422);
        $this->postJson('/anima/api/replay', $payload)->assertStatus(429);
    }
}
