<?php

namespace App\Http\Middleware;

use Anima\Traits\BypassesReplaySignatures;
use Closure;
use Illuminate\Http\Request;

class VerifyDummyWebhookSignature
{
    use BypassesReplaySignatures;

    public function handle(Request $request, Closure $next)
    {
        if ($this->isValidReplay($request)) {
            return $next($request);
        }

        $signature = $request->header('X-Hub-Signature-256');
        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, 'dummy_webhook_secret');

        if (! hash_equals($expectedSignature, (string) $signature)) {
            return response()->json(['error' => 'Unauthorized signature'], 401);
        }

        return $next($request);
    }
}
