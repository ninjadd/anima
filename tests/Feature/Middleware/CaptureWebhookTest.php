<?php

namespace Anima\Tests\Feature\Middleware;

use Anima\Contracts\PayloadStorageInterface;
use Anima\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class CaptureWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 3) . '/database/migrations/create_anima_entries_table.php.stub';
        $migration->up();
    }

    #[Test]
    public function it_captures_incoming_webhook_request_and_response(): void
    {
        Route::post('/webhooks/payment', function () {
            return response()->json(['status' => 'received', 'code' => 100], 200);
        })->middleware('anima.capture');

        $response = $this->postJson('/webhooks/payment', [
            'order_id' => 12345,
            'customer' => 'alice',
        ], [
            'X-Webhook-Signature' => 'sig_abc123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'received', 'code' => 100]);

        $storage = $this->app->make(PayloadStorageInterface::class);
        $results = $storage->paginate();

        $this->assertSame(1, $results['total']);

        $entry = $results['data'][0];
        $this->assertSame('POST', $entry['method']);
        $this->assertStringContainsString('/webhooks/payment', $entry['uri']);
        $this->assertSame(['order_id' => 12345, 'customer' => 'alice'], $entry['payload']);
        $this->assertSame(200, $entry['response_status']);
        $this->assertSame(['status' => 'received', 'code' => 100], $entry['response_body']);
        $this->assertIsFloat($entry['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $entry['duration_ms']);
        $this->assertFalse($entry['is_synthetic']);

        $lowercasedHeaders = array_change_key_case($entry['headers'], CASE_LOWER);
        $this->assertArrayHasKey('x-webhook-signature', $lowercasedHeaders);
        $this->assertSame(['sig_abc123'], (array) $lowercasedHeaders['x-webhook-signature']);
    }

    #[Test]
    public function it_redacts_sensitive_headers_but_preserves_others(): void
    {
        Route::post('/webhooks/secure', function () {
            return response()->json(['status' => 'received'], 200);
        })->middleware('anima.capture');

        $response = $this->postJson('/webhooks/secure', ['order_id' => 1], [
            'Authorization' => 'Bearer super-secret-token',
            'Cookie' => 'session=abc123',
            'X-Webhook-Signature' => 'sig_abc123',
        ]);

        $response->assertStatus(200);

        $storage = $this->app->make(PayloadStorageInterface::class);
        $entry = $storage->paginate()['data'][0];

        $headers = array_change_key_case($entry['headers'], CASE_LOWER);

        $this->assertSame(['[REDACTED]'], (array) $headers['authorization']);
        $this->assertSame(['[REDACTED]'], (array) $headers['cookie']);
        $this->assertSame(['sig_abc123'], (array) $headers['x-webhook-signature']);
    }

    #[Test]
    public function it_captures_tags_and_synthetic_flags_from_middleware_parameters_and_headers(): void
    {
        Route::post('/webhooks/stripe', function () {
            return response()->json(['ok' => true]);
        })->middleware('anima.capture:stripe,billing');

        $response = $this->postJson('/webhooks/stripe', [
            'type' => 'invoice.paid',
        ], [
            'X-Anima-Tag' => 'custom-tag, finance',
            'X-Anima-Synthetic' => 'true',
        ]);

        $response->assertStatus(200);

        $storage = $this->app->make(PayloadStorageInterface::class);
        $results = $storage->paginate();

        $this->assertSame(1, $results['total']);
        $entry = $results['data'][0];

        $this->assertTrue($entry['is_synthetic']);
        $this->assertIsArray($entry['tags']);
        $this->assertContains('stripe', $entry['tags']);
        $this->assertContains('billing', $entry['tags']);
        $this->assertContains('custom-tag', $entry['tags']);
        $this->assertContains('finance', $entry['tags']);
    }

    #[Test]
    public function it_does_not_capture_when_anima_is_disabled(): void
    {
        config(['anima.enabled' => false]);

        Route::post('/webhooks/disabled', function () {
            return response()->json(['status' => 'ok']);
        })->middleware('anima.capture');

        $response = $this->postJson('/webhooks/disabled', ['test' => 'disabled']);
        $response->assertStatus(200);

        $storage = $this->app->make(PayloadStorageInterface::class);
        $this->assertSame(0, $storage->paginate()['total']);
    }

    #[Test]
    public function it_captures_error_responses_gracefully(): void
    {
        Route::post('/webhooks/failing', function () {
            return response()->json(['error' => 'Internal server error'], 500);
        })->middleware('anima.capture');

        $response = $this->postJson('/webhooks/failing', ['data' => 'broken']);
        $response->assertStatus(500);

        $storage = $this->app->make(PayloadStorageInterface::class);
        $results = $storage->paginate();

        $this->assertSame(1, $results['total']);
        $entry = $results['data'][0];

        $this->assertSame(500, $entry['response_status']);
        $this->assertSame(['error' => 'Internal server error'], $entry['response_body']);
    }
}
