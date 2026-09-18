<?php

namespace Anima\Http\Controllers;

use Anima\Contracts\RequestSynthesizerInterface;
use Anima\Http\Middleware\CaptureWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplayController
{
    /**
     * Create a new replay controller instance.
     */
    public function __construct(
        protected RequestSynthesizerInterface $synthesizer
    ) {}

    /**
     * Synthesize and dispatch a modified webhook request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uri' => 'required|string',
            'method' => 'required|string',
            'headers' => 'nullable|array',
            'body' => 'nullable',
        ]);

        $uri = $validated['uri'];
        $method = $validated['method'];
        $headers = $validated['headers'] ?? [];
        $rawBody = $validated['body'] ?? null;

        if (! $this->isAllowedDestination($uri, $method)) {
            return response()->json([
                'message' => 'Replay destination must be a route tagged with the anima.capture middleware.',
            ], 422);
        }

        $body = null;
        if (is_array($rawBody)) {
            $body = json_encode($rawBody);
        } elseif ($rawBody !== null) {
            $body = (string) $rawBody;
        }

        $result = $this->synthesizer->synthesize($uri, $method, $headers, $body);

        return response()->json($result);
    }

    /**
     * Determine whether the given URI/method may be targeted by a replay.
     *
     * @param  string  $uri
     * @param  string  $method
     * @return bool
     */
    protected function isAllowedDestination(string $uri, string $method): bool
    {
        if (! config('anima.replay.restrict_to_captured_routes', true)) {
            return true;
        }

        $path = '/' . ltrim((string) parse_url($uri, PHP_URL_PATH), '/');

        try {
            $route = app('router')->getRoutes()->match(
                Request::create($path, strtoupper($method))
            );
        } catch (\Throwable $e) {
            return false;
        }

        // A route may carry the capture middleware either by its 'anima.capture'
        // alias (optionally with ':tag,tag' parameters) or by its FQCN, since
        // gatherMiddleware() returns whichever form the route registered.
        $captureMiddleware = ['anima.capture', CaptureWebhook::class];

        foreach ($route->gatherMiddleware() as $middleware) {
            $base = explode(':', $middleware, 2)[0];

            if (in_array($base, $captureMiddleware, true)) {
                return true;
            }
        }

        return false;
    }
}
