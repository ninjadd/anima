<?php

namespace Anima\Contracts;

interface RequestSynthesizerInterface
{
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
    public function synthesize(string $uri, string $method, array $headers = [], ?string $body = null): array;
}
