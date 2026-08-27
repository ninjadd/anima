<?php

namespace Anima\Services;

use Anima\Contracts\RequestSynthesizerInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request as LaravelRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class KernelRequestSynthesizer implements RequestSynthesizerInterface
{
    /**
     * Create a new kernel request synthesizer instance.
     */
    public function __construct(
        protected Kernel $kernel,
        protected Application $app
    ) {}

    /**
     * Synthesize and dispatch an HTTP request directly through the application Kernel.
     *
     * @param string $uri
     * @param string $method
     * @param array<string, mixed> $headers
     * @param string|null $body
     * @return array{
     *     status_code: int,
     *     headers: array<string, array<int, string>>,
     *     body: string|null,
     *     duration_ms: float,
     *     is_synthetic: bool
     * }
     */
    public function synthesize(string $uri, string $method, array $headers = [], ?string $body = null): array
    {
        $server = $this->transformHeadersToServerVars($headers);

        $server['HTTP_X_ANIMA_REPLAY'] = 'true';
        $server['HTTP_X_ANIMA_SYNTHETIC'] = 'true';

        $symfonyRequest = SymfonyRequest::create(
            $uri,
            strtoupper($method),
            [],
            [],
            [],
            $server,
            $body
        );

        $laravelRequest = LaravelRequest::createFromBase($symfonyRequest);

        $startTime = microtime(true);

        $previousRequest = $this->app->bound('request') ? $this->app->make('request') : null;

        $response = $this->kernel->handle($laravelRequest);

        $this->kernel->terminate($laravelRequest, $response);

        if ($previousRequest !== null) {
            $this->app->instance('request', $previousRequest);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $responseBody = method_exists($response, 'getContent') ? $response->getContent() : null;

        return [
            'status_code' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $responseBody !== false ? $responseBody : null,
            'duration_ms' => $durationMs,
            'is_synthetic' => true,
        ];
    }

    /**
     * Transform an associative array of headers into Symfony $_SERVER variables.
     *
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    protected function transformHeadersToServerVars(array $headers): array
    {
        $server = [];

        foreach ($headers as $key => $value) {
            $headerVal = is_array($value) ? implode(', ', $value) : (string) $value;
            $normalizedKey = strtoupper(str_replace('-', '_', $key));

            if ($normalizedKey === 'CONTENT_TYPE') {
                $server['CONTENT_TYPE'] = $headerVal;
            } elseif ($normalizedKey === 'CONTENT_LENGTH') {
                $server['CONTENT_LENGTH'] = $headerVal;
            } elseif ($normalizedKey === 'CONTENT_MD5') {
                $server['CONTENT_MD5'] = $headerVal;
            } elseif ($normalizedKey === 'AUTHORIZATION') {
                $server['HTTP_AUTHORIZATION'] = $headerVal;
            } else {
                $server['HTTP_' . $normalizedKey] = $headerVal;
            }
        }

        return $server;
    }
}
