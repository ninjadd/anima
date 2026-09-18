# Anima Webhook Interceptor & Synthetic Replay Studio (`scry/anima`)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/scry/anima.svg?style=flat-square)](https://packagist.org/packages/scry/anima)
[![Latest Tag](https://img.shields.io/github/v/tag/ninjadd/anima?label=tag&style=flat-square)](https://github.com/ninjadd/anima/tags)
[![Total Downloads](https://img.shields.io/packagist/dt/scry/anima.svg?style=flat-square)](https://packagist.org/packages/scry/anima)
[![Tests Passing](https://img.shields.io/badge/Tests-65%20Passing-emerald.svg?style=flat-square)](https://github.com/ninjadd/anima)
[![License](https://img.shields.io/github/license/ninjadd/anima?style=flat-square)](LICENSE)
[![Laravel Support](https://img.shields.io/badge/Laravel-10_%7C_11_%7C_12_%7C_13%2B-red.svg?style=flat-square)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.2_%7C_8.3_%7C_8.4_%7C_8.5-blue.svg?style=flat-square)](https://php.net)

---

## Introduction

**Anima** is a developer-centric, reactive webhook interceptor and synthetic request replay studio for Laravel applications. It embeds an interactive split-pane Workbench powered by Vue 3 and Monaco Editor directly into your web browser, giving developers an instant interface to capture incoming webhooks, inspect payload trees, mutate headers and JSON payloads, and replay synthetic requests directly through Laravel's HTTP Kernel without network latency.

Designed for modern API development, Anima includes built-in cryptographic signature bypassing, swappable storage drivers (**Database**, **Isolated SQLite**, and **Redis**), and non-recursive replay loop prevention.

---

## Table of Contents

- [Features and Workbench Capabilities](#features-and-workbench-capabilities)
  - [1. Transparent Interception Middleware](#1-transparent-interception-middleware)
  - [2. In-Memory Kernel Request Synthesizer](#2-in-memory-kernel-request-synthesizer)
  - [3. Cryptographic Signature Bypassing](#3-cryptographic-signature-bypassing)
  - [4. Swappable Polymorphic Storage Drivers](#4-swappable-polymorphic-storage-drivers)
  - [5. Embedded Vue 3 & Monaco Editor Workbench](#5-embedded-vue-3--monaco-editor-workbench)
  - [6. Request Context & Header Inspector](#6-request-context--header-inspector)
  - [7. Light and Dark Theme Support](#7-light-and-dark-theme-support)
- [Supported Storage Drivers](#supported-storage-drivers)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage Guide](#usage-guide)
  - [Attaching Interception Middleware](#attaching-interception-middleware)
  - [Integrating Signature Bypass Trait](#integrating-signature-bypass-trait)
- [Security Considerations & Environment Guards](#security-considerations--environment-guards)
  - [Dashboard & API Authorization](#dashboard--api-authorization)
  - [Sensitive Header Redaction](#sensitive-header-redaction)
  - [Replay Destination Restrictions](#replay-destination-restrictions)
  - [Rate Limiting](#rate-limiting)
  - [Cryptographic Signature Bypassing](#cryptographic-signature-bypassing)
  - [Long-Running Workers (Octane, Swoole, RoadRunner)](#long-running-workers-octane-swoole-roadrunner)
- [Quickstart and Local Verification](#quickstart-and-local-verification)
- [Multi-Driver Docker Compose Environment](#multi-driver-docker-compose-environment)
- [Running Automated Tests](#running-automated-tests)
- [License](#license)

---

## Features and Workbench Capabilities

### 1. Transparent Interception Middleware
- **Non-Blocking Capture:** Seamlessly intercepts incoming HTTP requests, recording raw request bodies, HTTP headers, response status codes, execution durations, and provider tags without altering upstream response flows.
- **Tagged Webhook Categorization:** Add provider tags directly to route middleware (`anima.capture:stripe,billing`) to filter and segment events in the workbench.
- **Infinite Loop Guard:** Automatically detects internal synthetic replays (`X-Anima-Replay: true`) to prevent recursive capture loops.

### 2. In-Memory Kernel Request Synthesizer
- **Zero-Network In-Memory Replays:** Executes synthetic HTTP requests directly through Laravel's `Illuminate\Contracts\Http\Kernel` in memory without making outbound cURL or socket calls.
- **Precise Duration Metrics:** Captures real execution duration in milliseconds (`ms`), HTTP response status codes, and formatted response bodies.

### 3. Cryptographic Signature Bypassing
- **Environment-Aware Trait:** The `BypassesReplaySignatures` trait enables seamless replay testing of modified payloads without failing external HMAC signature verification (e.g., Stripe, GitHub).
- **Production Guard:** Hardcoded to reject signature bypassing in `production` environments to eliminate header spoofing vulnerabilities.

### 4. Swappable Polymorphic Storage Drivers
- **Database Driver (`database`):** Stores webhook events in your primary database connection with configurable table names and timestamp indexing.
- **Isolated SQLite Driver (`sqlite`):** Automatically provisions an isolated SQLite database file and schema without modifying host application migrations.
- **Redis Driver (`redis`):** High-speed temporal storage utilizing Redis Hashes and Sorted Sets with automatic TTL key expiration. Known limitation: filtered queries (search/tag/status) scan the index newest-first and degrade with entry count, since Redis has no secondary index for these fields. `storage.redis.max_filter_scan` bounds that cost by capping how many recent entries a filtered query will scan (the API response's `truncated` flag reports when a query hit that cap) — prefer the Database or SQLite driver for large capture histories with heavy filtered querying.

### 5. Embedded Vue 3 & Monaco Editor Workbench
- **Split-Pane Architecture:** Left-pane scrollable event feed paired with a right-pane request/response inspection studio.
- **Monaco JSON Editor:** Full VS Code editing experience with syntax highlighting, automatic JSON formatting, and reactive document synchronization.
- **Replay Result Slide-Over:** Real-time modal detailing synthetic response status codes, execution durations, and formatted payload viewers with copy-to-clipboard actions.
- **Live-Updating Feed:** The dashboard polls for newly captured webhooks (interval configurable via `poll_interval`, disable with `0`) and reflects real connection health in the header — "Listening for Webhooks" while polling succeeds, "Reconnecting…" if it starts failing.
- **Shareable, Deep-Linkable Entries:** Selecting an event updates the URL (`/anima/{id}`), so refreshing, sharing a link, or using browser back/forward returns to the same entry instead of resetting to the first page.

### 6. Request Context & Header Inspector
- **Dynamic Header Manipulation:** Add, edit, remove, and reset request headers before triggering synthetic replays.
- **URI & Method Customization:** Test altered HTTP verbs (`POST`, `GET`, `PUT`, `PATCH`, `DELETE`) and custom relative or absolute endpoints.

### 7. Light and Dark Theme Support
- **Dual Visual Modes:** Fully styled for both high-contrast Dark and modern Light modes.
- **Monaco Theme Sync:** Monaco Editor automatically synchronizes between `vs-dark` and `vs` with persistent `localStorage` theme preference.

---

## Supported Storage Drivers

| Storage Driver | Schema Isolation | TTL Expiration | Query Filtering | Zero Migration Setup |
| :--- | :---: | :---: | :---: | :---: |
| **Database (`database`)** | Primary DB | Configurable via Purge | Supported | Requires Migration Stub |
| **SQLite (`sqlite`)** | Isolated File | Configurable via Purge | Supported | Auto-Provisioning |
| **Redis (`redis`)** | In-Memory | Native Key TTL | Supported | Auto-Provisioning |

---

## Installation

Install the package via Composer:

```bash
composer require scry/anima
```

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=anima-config
```

Publish and run the database migration (required only when using the `database` driver):

```bash
php artisan vendor:publish --tag=anima-migrations
php artisan migrate
```

Optionally publish compiled frontend assets and Blade views:

```bash
php artisan vendor:publish --tag=anima-assets --force
php artisan vendor:publish --tag=anima-views
```

---

## Configuration

In `config/anima.php`:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    | Enable or disable webhook interception and workbench access.
    */
    'enabled' => env('ANIMA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route Path
    |--------------------------------------------------------------------------
    | The base URI path where the Anima Workbench is accessible.
    */
    'path' => env('ANIMA_PATH', 'anima'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    | Safe-by-default environment constraints. Access in other environments
    | requires registering an Anima::auth(Closure) gate.
    */
    'allowed_environments' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Live Feed Polling
    |--------------------------------------------------------------------------
    | How often (in seconds) the dashboard polls for newly captured entries
    | while viewing the default, unfiltered first page. Set to 0 to disable
    | polling and rely on manual refresh only.
    */
    'poll_interval' => env('ANIMA_POLL_INTERVAL', 8),

    /*
    |--------------------------------------------------------------------------
    | Redacted Headers
    |--------------------------------------------------------------------------
    | Header names whose values should be replaced with "[REDACTED]".
    */
    'redact_headers' => [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-csrf-token',
        'x-xsrf-token',
        'stripe-signature',
        'x-hub-signature',
        'x-hub-signature-256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Replay Destination Restrictions
    |--------------------------------------------------------------------------
    | Restrict replays to routes carrying the anima.capture middleware.
    */
    'replay' => [
        'restrict_to_captured_routes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    | Throttling limits applied to purge and replay endpoints.
    */
    'rate_limits' => [
        'replay' => '30,1',
        'purge' => '10,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Driver Configuration
    |--------------------------------------------------------------------------
    | Supported drivers: "database", "sqlite", "redis"
    */
    'storage' => [
        'driver' => env('ANIMA_STORAGE_DRIVER', 'database'),

        'database' => [
            'connection' => env('ANIMA_DB_CONNECTION', null),
            'table' => env('ANIMA_DB_TABLE', 'anima_entries'),
        ],

        'sqlite' => [
            'database' => env('ANIMA_SQLITE_PATH', storage_path('anima/anima.sqlite')),
            'table' => env('ANIMA_SQLITE_TABLE', 'anima_entries'),
        ],

        'redis' => [
            'connection' => env('ANIMA_REDIS_CONNECTION', 'default'),
            'prefix' => env('ANIMA_REDIS_PREFIX', 'anima:entries'),
            'ttl' => env('ANIMA_REDIS_TTL', 86400),
            'max_filter_scan' => env('ANIMA_REDIS_MAX_FILTER_SCAN', 5000),
        ],
    ],
];
```

---

## Usage Guide

### Attaching Interception Middleware

Attach the `anima.capture` middleware alias to any webhook route. You can pass optional comma-separated tags to categorize entries:

```php
use Illuminate\Support\Facades\Route;

// Standard capture
Route::post('/webhooks/payment', [PaymentWebhookController::class, 'handle'])
    ->middleware('anima.capture');

// Capture with provider tags
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('anima.capture:stripe,billing');

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->middleware('anima.capture:github,vcs');
```

---

### Integrating Signature Bypass Trait

When replaying altered payloads, external HMAC signatures will naturally fail validation. Use the `BypassesReplaySignatures` trait inside your webhook verification middleware:

```php
namespace App\Http\Middleware;

use Anima\Traits\BypassesReplaySignatures;
use Closure;
use Illuminate\Http\Request;

class VerifyStripeSignature
{
    use BypassesReplaySignatures;

    public function handle(Request $request, Closure $next)
    {
        // Bypass signature verification during local/testing synthetic replays
        if ($this->isValidReplay($request)) {
            return $next($request);
        }

        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();
        $secret = config('services.stripe.webhook_secret');

        if (! $this->verifySignature($payload, $signature, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
```

---

## Security Considerations & Environment Guards

Anima captures and can replay live HTTP traffic — including whatever headers and payloads pass through your webhook routes — so it ships secure-by-default rather than relying on you to lock it down. Each guard below is independently configurable in `config/anima.php`.

### Dashboard & API Authorization

The `/anima` dashboard and every `/anima/api/*` endpoint (entry listing, entry deletion/purge, and replay) are gated by `Anima::check()`. By default, that check passes only when the application is running in an environment listed in `allowed_environments` (default: `local`, `testing`) — **including in `production`, where it fails closed**. Any request outside an allowed environment receives `403 Unauthorized` before it reaches a controller.

To allow authorized access in `production` (or any other environment), register a callback in a service provider's `boot()` method, mirroring Laravel Telescope/Horizon's `::auth()` pattern:

```php
use Anima\Anima;

Anima::auth(function ($request) {
    return $request->user()?->isAdmin() ?? false;
});
```

> [!CAUTION]
> **AUTHORIZATION GUARD**
> Don't add `'production'` to `allowed_environments`, and don't write an `Anima::auth()` callback that always returns `true`. Anyone who passes this check can read every captured webhook (including headers) and trigger synthetic replays — see below.

### Sensitive Header Redaction

Request headers are redacted before a captured entry is persisted. Any header listed in `redact_headers` (case-insensitive match) is stored as `[REDACTED]` instead of its real value. The default list covers common auth and provider signature headers:

```php
'redact_headers' => [
    'authorization',
    'cookie',
    'set-cookie',
    'x-api-key',
    'x-csrf-token',
    'x-xsrf-token',
    'stripe-signature',
    'x-hub-signature',
    'x-hub-signature-256',
],
```

Extend this list with any additional secret or signature headers your webhook providers use.

### Replay Destination Restrictions

`POST /anima/api/replay` dispatches a synthetic request through your application's own HTTP Kernel — attacker-controlled method, headers, and body included — so it must not be usable to forge requests against routes it wasn't meant to touch. By default (`replay.restrict_to_captured_routes`), a replay is only permitted when its target URI resolves to a route carrying the `anima.capture` middleware, i.e. a route Anima already captures traffic for. Any other destination returns `422 Unprocessable Entity` without dispatching the request.

### Rate Limiting

The purge (`DELETE /anima/api/entries`) and replay (`POST /anima/api/replay`) endpoints are throttled via `rate_limits` (`"max attempts,decay minutes"`, default `10,1` and `30,1` respectively), limiting how much damage an authorized-but-compromised session can do.

### Cryptographic Signature Bypassing

> [!CAUTION]
> **ENVIRONMENT SECURITY GUARD**
> The `BypassesReplaySignatures` trait is strictly locked to `local` and `testing` environments (`app()->environment('local', 'testing')`). In `production` environments, `isValidReplay()` is hardcoded to return `false`.
>
> The bypass itself is gated on the `anima_synthetic_replay` request *attribute*, which only `KernelRequestSynthesizer` sets, never on a header. Headers on a real inbound request (for example one arriving over an ngrok/Cloudflare tunnel during local webhook development) are attacker-controlled, so this check must never be changed to read a header — doing so would let anyone who can reach the endpoint bypass signature verification.

### Long-Running Workers (Octane, Swoole, RoadRunner)

`KernelRequestSynthesizer` dispatches synthetic requests through the same in-process Kernel used to serve the current request. The container's `request` binding is saved before dispatch and restored immediately after, so a replay cannot leak its synthetic request context into the next request served by the same long-running worker process.

---

## Quickstart and Local Verification

1. Start your local development server:

```bash
php artisan serve
# or with Orchestra Testbench
./vendor/bin/testbench serve --port=8000
```

2. Open your browser and navigate to:

```text
http://localhost:8000/anima
```

3. Send a test webhook from your terminal:

```bash
curl -X POST http://localhost:8000/api/webhooks/stripe \
  -H "Content-Type: application/json" \
  -H "Stripe-Signature: t=123,v1=test_sig" \
  -d '{"id": "evt_123", "type": "payment_intent.succeeded", "data": {"object": {"amount": 4900, "currency": "usd"}}}'
```

4. The webhook event will appear in the **Captured Events** feed. Select it, modify the JSON payload in Monaco Editor, adjust headers, and click **Synthesize & Replay**.

---

## Multi-Driver Docker Compose Environment

A multi-driver Docker Compose stack is bundled for testing across storage backends:

```bash
# Start Redis, PostgreSQL, MySQL, and MariaDB containers
docker compose up -d

# Stop containers
docker compose down
```

Services included:
- **Redis 7** on port `6379`
- **PostgreSQL 16** on port `5432`
- **MySQL 8.0** on port `3306`
- **MariaDB 11** on port `3307`

---

## Running Automated Tests

Run the PHPUnit test suite:

```bash
./vendor/bin/phpunit
```

All 65 tests (270 assertions) verify:
- Storage drivers (`DatabaseStorageDriver`, `SqliteStorageDriver`, `RedisStorageDriver`) and `StorageManager`.
- Webhook capture middleware with route tags and synthetic replay loop prevention.
- Kernel request synthesizer in-memory dispatch, metric tracking, and request singleton restoration.
- Signature bypassing safety checks and environment guards.
- Workbench HTTP controllers, Blade templates, asset streaming, and REST API endpoints.
- Authorization gate defaults (`Anima::auth()`, default-deny in production, allowed environments).
- Sensitive header redaction and safe replay destination verification.
- SQLite table identifier safety and endpoint rate limiting.

---

## License

This package is open-sourced software licensed under the **[MIT License](LICENSE)**.
