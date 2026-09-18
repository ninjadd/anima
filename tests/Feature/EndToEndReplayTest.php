<?php

namespace Anima\Tests\Feature;

use Anima\Contracts\PayloadStorageInterface;
use Anima\Contracts\RequestSynthesizerInterface;
use Anima\Tests\TestCase;
use Anima\Traits\BypassesReplaySignatures;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class DummyVerifyWebhookSignature
{
    use BypassesReplaySignatures;

    public function handle(Request $request, Closure $next)
    {
        if ($this->isValidReplay($request)) {
            return $next($request);
        }

        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, 'webhook_secret_key');

        if (! hash_equals($expectedSignature, (string) $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}

class EndToEndReplayTest extends TestCase
{
    protected PayloadStorageInterface $storage;
    protected RequestSynthesizerInterface $synthesizer;

    protected function setUp(): void
    {
        parent::setUp();

        $migration = require dirname(__DIR__, 2) . '/database/migrations/create_anima_entries_table.php.stub';
        $migration->up();

        $this->storage = $this->app->make(PayloadStorageInterface::class);
        $this->synthesizer = $this->app->make(RequestSynthesizerInterface::class);

        Route::post('/api/webhooks/github', function (Request $request) {
            return response()->json([
                'status' => 'success',
                'processed_event' => $request->input('event'),
                'sender' => $request->input('sender.login'),
            ], 200);
        })->middleware([DummyVerifyWebhookSignature::class, 'anima.capture:github']);
    }

    #[Test]
    public function it_executes_full_end_to_end_interception_modification_and_signature_bypassed_replay(): void
    {
        // Step A (Interception): Send valid initial signed webhook
        $originalPayload = [
            'event' => 'issue_opened',
            'sender' => ['login' => 'alice'],
        ];
        $rawJson = json_encode($originalPayload);
        $validSignature = 'sha256=' . hash_hmac('sha256', $rawJson, 'webhook_secret_key');

        $response = $this->call(
            'POST',
            '/api/webhooks/github',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $validSignature,
            ],
            $rawJson
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'processed_event' => 'issue_opened',
                'sender' => 'alice',
            ]);

        // Verify captured into storage
        $storedEntries = $this->storage->paginate(10);
        $this->assertSame(1, $storedEntries['total']);

        $captured = $storedEntries['data'][0];
        $this->assertSame('POST', $captured['method']);
        $this->assertSame($originalPayload, $captured['payload']);
        $this->assertFalse($captured['is_synthetic']);

        // Step B (Modification): Modify payload (which would invalidate HMAC signature)
        $modifiedPayload = [
            'event' => 'issue_closed',
            'sender' => ['login' => 'bob'],
        ];
        $modifiedJson = json_encode($modifiedPayload);

        // Step C (Synthesis): Replay through KernelRequestSynthesizer
        $syntheticResult = $this->synthesizer->synthesize(
            uri: $captured['uri'],
            method: $captured['method'],
            headers: $captured['headers'],
            body: $modifiedJson
        );

        // Step D (Verification):
        // 1. Signature check was bypassed due to X-Anima-Replay and safe environment
        $this->assertSame(200, $syntheticResult['status_code']);
        $this->assertTrue($syntheticResult['is_synthetic']);

        $resultBody = json_decode($syntheticResult['body'], true);
        $this->assertSame('success', $resultBody['status']);
        $this->assertSame('issue_closed', $resultBody['processed_event']);
        $this->assertSame('bob', $resultBody['sender']);

        // 2. Storage count remains 1 (prevented infinite capture loop)
        $this->assertSame(1, $this->storage->paginate(10)['total']);
    }

    #[Test]
    public function it_strictly_enforces_signatures_in_production_environment(): void
    {
        // Simulate production environment
        $this->app['env'] = 'production';

        $rawJson = json_encode(['event' => 'tampered_event']);

        // Synthesize request with X-Anima-Replay header present
        $result = $this->synthesizer->synthesize(
            uri: '/api/webhooks/github',
            method: 'POST',
            headers: [
                'Content-Type' => 'application/json',
                'X-Hub-Signature-256' => 'invalid_or_stale_signature',
            ],
            body: $rawJson
        );

        // Must fail with 401 in production environment even if X-Anima-Replay is set
        $this->assertSame(401, $result['status_code']);
        $this->assertStringContainsString('Invalid signature', $result['body']);
    }

    #[Test]
    public function it_rejects_a_spoofed_replay_header_sent_over_a_real_http_request(): void
    {
        // Simulates an external attacker (e.g. reaching a local dev server exposed
        // via an ngrok/Cloudflare tunnel) forging the X-Anima-Replay header on a
        // real inbound request, without ever going through KernelRequestSynthesizer.
        // The bypass must only trigger from the internal 'anima_synthetic_replay'
        // request attribute, which a raw HTTP request can never set.
        $forgedPayload = ['event' => 'forged_by_attacker'];
        $rawJson = json_encode($forgedPayload);

        $response = $this->call(
            'POST',
            '/api/webhooks/github',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ANIMA_REPLAY' => 'true',
                'HTTP_X_HUB_SIGNATURE_256' => 'totally_bogus_signature',
            ],
            $rawJson
        );

        $response->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }
}
