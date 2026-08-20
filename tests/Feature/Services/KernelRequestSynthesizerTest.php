<?php

namespace Anima\Tests\Feature\Services;

use Anima\Contracts\RequestSynthesizerInterface;
use Anima\Services\KernelRequestSynthesizer;
use Anima\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class KernelRequestSynthesizerTest extends TestCase
{
    protected RequestSynthesizerInterface $synthesizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->synthesizer = $this->app->make(RequestSynthesizerInterface::class);
    }

    #[Test]
    public function it_resolves_synthesizer_from_container(): void
    {
        $this->assertInstanceOf(RequestSynthesizerInterface::class, $this->synthesizer);
        $this->assertInstanceOf(KernelRequestSynthesizer::class, $this->synthesizer);
    }

    #[Test]
    public function it_synthesizes_post_request_and_captures_response(): void
    {
        Route::post('/api/webhook/test', function (Request $request) {
            return response()->json([
                'received_payload' => $request->all(),
                'custom_header' => $request->header('X-Custom-Header'),
                'is_replay' => $request->header('X-Anima-Replay'),
                'is_synthetic' => $request->header('X-Anima-Synthetic'),
            ], 200, ['X-Server-Time' => '123456']);
        });

        $result = $this->synthesizer->synthesize(
            uri: '/api/webhook/test',
            method: 'POST',
            headers: [
                'Content-Type' => 'application/json',
                'X-Custom-Header' => 'test-value-42',
            ],
            body: json_encode(['event' => 'payment.success', 'amount' => 9900])
        );

        $this->assertSame(200, $result['status_code']);
        $this->assertTrue($result['is_synthetic']);
        $this->assertIsFloat($result['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $result['duration_ms']);

        $decodedBody = json_decode($result['body'], true);
        $this->assertIsArray($decodedBody);
        $this->assertSame('payment.success', $decodedBody['received_payload']['event']);
        $this->assertSame(9900, $decodedBody['received_payload']['amount']);
        $this->assertSame('test-value-42', $decodedBody['custom_header']);
        $this->assertSame('true', $decodedBody['is_replay']);
        $this->assertSame('true', $decodedBody['is_synthetic']);

        $this->assertArrayHasKey('x-server-time', array_change_key_case($result['headers'], CASE_LOWER));
    }

    #[Test]
    public function it_synthesizes_get_put_and_delete_methods(): void
    {
        Route::get('/api/items', function () {
            return response()->json(['items' => ['item1', 'item2']]);
        });

        Route::put('/api/items/1', function (Request $request) {
            return response()->json(['updated' => true, 'data' => $request->all()], 200);
        });

        Route::delete('/api/items/1', function () {
            return response()->json(null, 204);
        });

        // GET
        $getResult = $this->synthesizer->synthesize('/api/items', 'GET');
        $this->assertSame(200, $getResult['status_code']);
        $this->assertStringContainsString('item1', $getResult['body']);

        // PUT
        $putResult = $this->synthesizer->synthesize(
            '/api/items/1',
            'PUT',
            ['Content-Type' => 'application/json'],
            json_encode(['name' => 'updated item'])
        );
        $this->assertSame(200, $putResult['status_code']);
        $this->assertStringContainsString('updated item', $putResult['body']);

        // DELETE
        $deleteResult = $this->synthesizer->synthesize('/api/items/1', 'DELETE');
        $this->assertSame(204, $deleteResult['status_code']);
    }

    #[Test]
    public function it_captures_error_responses_accurately(): void
    {
        Route::post('/api/validate', function (Request $request) {
            if (! $request->has('required_field')) {
                return response()->json(['error' => 'Validation failed'], 422);
            }
            return response()->json(['success' => true]);
        });

        $result = $this->synthesizer->synthesize(
            uri: '/api/validate',
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode(['foo' => 'bar'])
        );

        $this->assertSame(422, $result['status_code']);
        $this->assertStringContainsString('Validation failed', $result['body']);
        $this->assertTrue($result['is_synthetic']);
    }
}
