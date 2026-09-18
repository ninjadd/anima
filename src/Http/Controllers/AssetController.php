<?php

namespace Anima\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController
{
    /**
     * Serve a compiled asset from the package distribution directory.
     *
     * @param  string  $path
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function show(Request $request, string $path): BinaryFileResponse
    {
        // Sanitize path to prevent directory traversal
        $path = ltrim($path, '/\\');
        if (str_contains($path, '..')) {
            throw new NotFoundHttpException('Asset not found.');
        }

        $baseDir = dirname(__DIR__, 3) . '/resources/dist';
        $fullPath = "{$baseDir}/{$path}";

        if (! file_exists($fullPath) || ! is_file($fullPath)) {
            // Check in published public assets directory as fallback
            $publicPath = public_path("vendor/anima/{$path}");
            if (file_exists($publicPath) && is_file($publicPath)) {
                $fullPath = $publicPath;
            } else {
                throw new NotFoundHttpException('Asset not found.');
            }
        }

        $response = response()->file($fullPath, [
            'Content-Type' => $this->getMimeType($fullPath),
            'Cache-Control' => 'public, max-age=31536000',
        ]);

        // Return value is unused; isNotModified() mutates $response in place,
        // setting a 304 status and stripping the body when it matches.
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Determine the MIME type of the given file path.
     *
     * @param  string  $path
     * @return string
     */
    protected function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'js' => 'text/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'svg' => 'image/svg+xml',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            default => 'text/plain; charset=utf-8',
        };
    }
}
