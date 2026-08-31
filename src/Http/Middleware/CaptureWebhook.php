<?php

namespace Anima\Http\Middleware;

use Anima\Managers\StorageManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureWebhook
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected StorageManager $storageManager
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$tags
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$tags): Response
    {
        if (! config('anima.enabled', true)) {
            return $next($request);
        }

        // Prevent infinite capture loops when executing synthetic replays
        $isReplay = strtolower((string) $request->header('X-Anima-Replay', '')) === 'true'
            || $request->header('X-Anima-Replay') === '1';

        if ($isReplay) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $this->capture($request, $response, $durationMs, $tags);

        return $response;
    }

    /**
     * Extract and persist request and response metadata.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  float  $durationMs
     * @param  array<int, string>  $middlewareTags
     * @return void
     */
    protected function capture(Request $request, Response $response, float $durationMs, array $middlewareTags = []): void
    {
        try {
            $rawBody = $request->getContent();
            $payload = null;

            if ($rawBody !== '' && $rawBody !== false && $rawBody !== null) {
                $decoded = json_decode($rawBody, true);
                $payload = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $rawBody;
            } elseif (! empty($request->all())) {
                $payload = $request->all();
            }

            $responseBody = null;
            if (method_exists($response, 'getContent')) {
                $rawResponse = $response->getContent();
                if ($rawResponse !== false && $rawResponse !== null && $rawResponse !== '') {
                    $decodedResponse = json_decode($rawResponse, true);
                    $responseBody = (json_last_error() === JSON_ERROR_NONE) ? $decodedResponse : $rawResponse;
                }
            }

            $resolvedTags = [];
            foreach ($middlewareTags as $tagArg) {
                foreach (explode(',', $tagArg) as $t) {
                    $t = trim($t);
                    if ($t !== '') {
                        $resolvedTags[] = $t;
                    }
                }
            }

            if ($headerTag = $request->header('X-Anima-Tag')) {
                foreach (explode(',', $headerTag) as $t) {
                    $t = trim($t);
                    if ($t !== '') {
                        $resolvedTags[] = $t;
                    }
                }
            }

            $resolvedTags = array_values(array_unique($resolvedTags));

            $isSynthetic = filter_var($request->header('X-Anima-Synthetic'), FILTER_VALIDATE_BOOLEAN)
                || $request->boolean('is_synthetic');

            $responseStatus = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

            $this->storageManager->store([
                'method' => $request->method(),
                'uri' => $request->fullUrl(),
                'headers' => $this->redactHeaders($request->headers->all()),
                'payload' => $payload,
                'response_status' => $responseStatus,
                'response_body' => $responseBody,
                'duration_ms' => $durationMs,
                'is_synthetic' => $isSynthetic,
                'tags' => ! empty($resolvedTags) ? $resolvedTags : null,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /**
     * Replace the values of sensitive headers with a redaction placeholder.
     *
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, array<int, string>>
     */
    protected function redactHeaders(array $headers): array
    {
        $redactList = array_map('strtolower', config('anima.redact_headers', []));

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $redactList, true)) {
                $headers[$key] = ['[REDACTED]'];
            }
        }

        return $headers;
    }
}
