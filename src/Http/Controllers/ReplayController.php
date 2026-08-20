<?php

namespace Anima\Http\Controllers;

use Anima\Contracts\RequestSynthesizerInterface;
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

        $body = null;
        if (is_array($rawBody)) {
            $body = json_encode($rawBody);
        } elseif ($rawBody !== null) {
            $body = (string) $rawBody;
        }

        $result = $this->synthesizer->synthesize($uri, $method, $headers, $body);

        return response()->json($result);
    }
}
