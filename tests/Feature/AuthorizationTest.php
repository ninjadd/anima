<?php

namespace Anima\Tests\Feature;

use Anima\Anima;
use Anima\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class AuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 2) . '/database/migrations/create_anima_entries_table.php.stub';
        $migration->up();
    }

    #[Test]
    public function it_allows_access_by_default_in_the_testing_environment(): void
    {
        $this->get('/anima')->assertStatus(200);
        $this->getJson('/anima/api/entries')->assertStatus(200);
    }

    #[Test]
    public function it_denies_access_in_production_with_no_custom_auth_callback(): void
    {
        $this->app['env'] = 'production';

        $this->get('/anima')->assertStatus(403);
        $this->getJson('/anima/api/entries')->assertStatus(403);
    }

    #[Test]
    public function it_serves_assets_in_production_without_authorization(): void
    {
        $this->app['env'] = 'production';

        $dist = dirname(__DIR__, 2) . '/resources/dist';
        if (! is_dir($dist)) {
            mkdir($dist, 0755, true);
        }
        file_put_contents($dist . '/auth-test-asset.js', 'console.log("anima asset");');

        try {
            $this->get('/anima/assets/auth-test-asset.js')->assertStatus(200);
        } finally {
            @unlink($dist . '/auth-test-asset.js');
        }
    }

    #[Test]
    public function it_defers_to_a_custom_auth_callback_when_registered(): void
    {
        $this->app['env'] = 'production';

        Anima::auth(fn (Request $request) => $request->header('X-Test-Token') === 'secret');

        $this->getJson('/anima/api/entries')->assertStatus(403);

        $this->getJson('/anima/api/entries', ['X-Test-Token' => 'secret'])
            ->assertStatus(200);
    }
}
