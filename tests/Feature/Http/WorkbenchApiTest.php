<?php

namespace Anima\Tests\Feature\Http;

use Anima\Contracts\PayloadStorageInterface;
use Anima\Http\Middleware\CaptureWebhook;
use Anima\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class WorkbenchApiTest extends TestCase
{
    protected PayloadStorageInterface $storage;
    protected string $testAssetDir;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 3) . '/database/migrations/create_anima_entries_table.php.stub';
        $migration->up();

        $this->storage = $this->app->make(PayloadStorageInterface::class);

        // Ensure dist directory exists with a test asset
        $this->testAssetDir = dirname(__DIR__, 3) . '/resources/dist';
        if (! is_dir($this->testAssetDir)) {
            mkdir($this->testAssetDir, 0755, true);
        }
        file_put_contents($this->testAssetDir . '/test-asset.js', 'console.log("anima test");');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testAssetDir . '/test-asset.js')) {
            @unlink($this->testAssetDir . '/test-asset.js');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_renders_workbench_blade_view(): void
    {
        $response = $this->get('/anima');

        $response->assertStatus(200)
            ->assertSee('<div id="app"></div>', false)
            ->assertSee('window.Anima =', false)
            ->assertSee('Anima - Webhook Interceptor & Replay Studio', false);
    }

    #[Test]
    public function it_serves_static_assets_with_proper_mime_types(): void
    {
        $response = $this->get('/anima/assets/test-asset.js');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'anima test',
            file_get_contents($response->baseResponse->getFile()->getPathname())
        );
    }

    #[Test]
    public function it_serves_assets_without_starting_a_session_or_cookies(): void
    {
        $response = $this->get('/anima/assets/test-asset.js');

        $response->assertStatus(200);
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->assertFalse($this->app['session']->isStarted());
    }

    #[Test]
    public function it_supports_conditional_requests_for_assets(): void
    {
        $first = $this->get('/anima/assets/test-asset.js');
        $first->assertStatus(200);

        $lastModified = $first->headers->get('Last-Modified');
        $this->assertNotEmpty($lastModified);

        $second = $this->get('/anima/assets/test-asset.js', [
            'If-Modified-Since' => $lastModified,
        ]);

        $second->assertStatus(304);
    }

    #[Test]
    public function it_blocks_directory_traversal_attempts_on_assets(): void
    {
        $response = $this->get('/anima/assets/../../config/anima.php');
        $response->assertStatus(404);
    }

    #[Test]
    public function it_lists_paginated_entries_via_api(): void
    {
        $this->storage->store([
            'method' => 'POST',
            'uri' => 'https://example.com/webhooks/stripe',
            'tags' => ['stripe'],
            'response_status' => 200,
        ]);

        $this->storage->store([
            'method' => 'GET',
            'uri' => 'https://example.com/webhooks/github',
            'tags' => ['github'],
            'response_status' => 200,
        ]);

        $response = $this->getJson('/anima/api/entries');

        $response->assertStatus(200)
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_rejects_non_positive_per_page_values(): void
    {
        $this->storage->store(['uri' => 'https://example.com/webhooks/stripe']);

        $zero = $this->getJson('/anima/api/entries?per_page=0');
        $zero->assertStatus(422);

        $negative = $this->getJson('/anima/api/entries?per_page=-5');
        $negative->assertStatus(422);
    }

    #[Test]
    public function it_rejects_per_page_values_above_the_upper_bound(): void
    {
        $this->storage->store(['uri' => 'https://example.com/webhooks/stripe']);

        $tooLarge = $this->getJson('/anima/api/entries?per_page=100000');
        $tooLarge->assertStatus(422);

        $atLimit = $this->getJson('/anima/api/entries?per_page=100');
        $atLimit->assertStatus(200);
    }

    #[Test]
    public function it_shows_single_entry_details_via_api(): void
    {
        $id = $this->storage->store([
            'method' => 'POST',
            'uri' => 'https://example.com/webhooks/orders',
            'payload' => ['order_id' => 9988],
            'response_status' => 200,
        ]);

        $response = $this->getJson("/anima/api/entries/{$id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $id,
                'method' => 'POST',
                'uri' => 'https://example.com/webhooks/orders',
                'payload' => ['order_id' => 9988],
            ]);

        $missing = $this->getJson('/anima/api/entries/missing-uuid-123');
        $missing->assertStatus(404);
    }

    #[Test]
    public function it_deletes_and_clears_entries_via_api(): void
    {
        $id1 = $this->storage->store(['uri' => 'https://example.com/1']);
        $id2 = $this->storage->store(['uri' => 'https://example.com/2']);

        $deleteSingle = $this->deleteJson("/anima/api/entries/{$id1}");
        $deleteSingle->assertStatus(200)->assertJson(['deleted' => true]);

        $this->assertNull($this->storage->find($id1));
        $this->assertNotNull($this->storage->find($id2));

        $clearAll = $this->deleteJson('/anima/api/entries');
        $clearAll->assertStatus(200)->assertJson(['purged' => true]);

        $this->assertSame(0, $this->storage->paginate()['total']);
    }

    #[Test]
    public function it_dispatches_synthetic_replay_via_api(): void
    {
        Route::post('/api/test-receiver', function (Request $request) {
            return response()->json([
                'received' => true,
                'echo' => $request->all(),
            ], 200);
        })->middleware('anima.capture');

        $response = $this->postJson('/anima/api/replay', [
            'uri' => '/api/test-receiver',
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['ping' => 'pong'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('is_synthetic', true);

        $body = json_decode($response->json('body'), true);
        $this->assertTrue($body['received']);
        $this->assertSame('pong', $body['echo']['ping']);
    }

    #[Test]
    public function it_dispatches_synthetic_replay_to_a_route_tagged_via_class_string_middleware(): void
    {
        Route::post('/api/class-middleware-receiver', function (Request $request) {
            return response()->json([
                'received' => true,
                'echo' => $request->all(),
            ], 200);
        })->middleware(CaptureWebhook::class);

        $response = $this->postJson('/anima/api/replay', [
            'uri' => '/api/class-middleware-receiver',
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['ping' => 'pong'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('is_synthetic', true);

        $body = json_decode($response->json('body'), true);
        $this->assertTrue($body['received']);
        $this->assertSame('pong', $body['echo']['ping']);
    }

    #[Test]
    public function it_dispatches_synthetic_replay_to_a_domain_scoped_route(): void
    {
        Route::domain('api.example.test')->post('/webhooks/stripe', function (Request $request) {
            return response()->json([
                'received' => true,
                'echo' => $request->all(),
            ], 200);
        })->middleware('anima.capture');

        $response = $this->postJson('/anima/api/replay', [
            'uri' => 'http://api.example.test/webhooks/stripe',
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['ping' => 'pong'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status_code', 200)
            ->assertJsonPath('is_synthetic', true);

        $body = json_decode($response->json('body'), true);
        $this->assertTrue($body['received']);
        $this->assertSame('pong', $body['echo']['ping']);
    }

    #[Test]
    public function it_rejects_replay_to_a_route_not_tagged_with_anima_capture(): void
    {
        $invoked = false;

        Route::post('/api/untagged-receiver', function (Request $request) use (&$invoked) {
            $invoked = true;

            return response()->json(['received' => true], 200);
        });

        $response = $this->postJson('/anima/api/replay', [
            'uri' => '/api/untagged-receiver',
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['ping' => 'pong'],
        ]);

        $response->assertStatus(422);
        $this->assertFalse($invoked);
    }
}
