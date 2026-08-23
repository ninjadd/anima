# Anima Webhook Interceptor & Synthetic Replay Studio (`scry/anima`)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/scry/anima.svg?style=flat-square)](https://packagist.org/packages/scry/anima)
[![Latest Tag](https://img.shields.io/github/v/tag/ninjadd/anima?label=tag&style=flat-square)](https://github.com/ninjadd/anima/tags)
[![Total Downloads](https://img.shields.io/packagist/dt/scry/anima.svg?style=flat-square)](https://packagist.org/packages/scry/anima)
[![Tests Passing](https://img.shields.io/badge/Tests-34%20Passing-emerald.svg?style=flat-square)](https://github.com/ninjadd/anima)
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
- **Redis Driver (`redis`):** High-speed temporal storage utilizing Redis Hashes and Sorted Sets with automatic TTL key expiration.

### 5. Embedded Vue 3 & Monaco Editor Workbench
- **Split-Pane Architecture:** Left-pane scrollable event feed paired with a right-pane request/response inspection studio.
- **Monaco JSON Editor:** Full VS Code editing experience with syntax highlighting, automatic JSON formatting, and reactive document synchronization.
- **Replay Result Slide-Over:** Real-time modal detailing synthetic response status codes, execution durations, and formatted payload viewers with copy-to-clipboard actions.

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

> [!CAUTION]
> **ENVIRONMENT SECURITY GUARD**
> The `BypassesReplaySignatures` trait is strictly locked to `local` and `testing` environments (`app()->environment('local', 'testing')`). In `production` environments, `isValidReplay()` is hardcoded to return `false`, preventing header spoofing attacks.

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

All 34 tests (195 assertions) verify:
- Storage drivers (`DatabaseStorageDriver`, `SqliteStorageDriver`, `RedisStorageDriver`) and `StorageManager`.
- Webhook capture middleware with route tags and synthetic replay loop prevention.
- Kernel request synthesizer in-memory dispatch and metric tracking.
- Signature bypassing safety checks and environment guards.
- Workbench HTTP controllers, Blade templates, asset streaming, and REST API endpoints.

---

## License

This package is open-sourced software licensed under the **[MIT License](LICENSE)**.
