# Anima

**Reactive Webhook Interceptor & Synthetic Request Replay Studio for Laravel**

Anima is a developer-centric Laravel package designed to capture, inspect, modify, and synthetically replay incoming webhooks without network overhead. It embeds an interactive split-pane Workbench powered by Vue 3 and the Monaco Editor directly within your Laravel application.

---

## Features

- **Transparent Interception Middleware:** Capture raw payloads, headers, response status codes, and execution durations in a non-blocking lifecycle.
- **In-Memory Kernel Synthesizer:** Replay captured or mutated webhook requests directly through Laravel's HTTP Kernel without external HTTP/cURL calls.
- **Cryptographic Signature Bypassing:** Built-in trait (`BypassesReplaySignatures`) allows developers to test mutated payloads locally without failing HMAC signature checks, guarded strictly against execution in production.
- **Swappable Storage Drivers:** Polymorphic storage architecture supporting standard SQL databases (`database`), isolated auto-bootstrapping local SQLite databases (`sqlite`), and Redis (`redis`) with configurable TTLs.
- **Embedded Vue 3 Workbench:** Modern dark-themed SPA featuring live event feeds, search and method filters, interactive header inspector, JSON payload editing via Monaco Editor, and slide-over execution metrics.
- **Zero-Config Asset Serving:** Stream precompiled assets securely or publish them directly to your application's public directory.

---

## Requirements

- **PHP:** 8.2 or higher
- **Laravel Framework:** 10.x, 11.x, 12.x, or 13.x

---

## Installation

Install the package via Composer:

```bash
composer require ninjadd/anima
```

Publish the package configuration file:

```bash
php artisan vendor:publish --tag=anima-config
```

Publish and run the database migrations (if using the `database` storage driver):

```bash
php artisan vendor:publish --tag=anima-migrations
php artisan migrate
```

Optionally, publish the frontend assets and Blade views:

```bash
php artisan vendor:publish --tag=anima-assets
php artisan vendor:publish --tag=anima-views
```

---

## Configuration

The published `config/anima.php` configuration file allows you to customize route paths, middleware, and storage drivers:

```php
return [
    /*
    | Master switch to enable or disable webhook interception and workbench access.
    */
    'enabled' => env('ANIMA_ENABLED', true),

    /*
    | The route URI prefix where the Anima Workbench and API endpoints are served.
    */
    'path' => env('ANIMA_PATH', 'anima'),

    /*
    | Middleware applied to the Anima Workbench and API routes.
    */
    'middleware' => [
        'web',
    ],

    /*
    | Storage drivers for captured webhooks: "database", "sqlite", or "redis".
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

## Usage

### 1. Intercepting Incoming Webhooks

Attach the `anima.capture` middleware to any route you wish to record. You can optionally provide provider tags to categorize incoming webhooks:

```php
use Illuminate\Support\Facades\Route;

// Simple route capture
Route::post('/webhooks/payment', [PaymentWebhookController::class, 'handle'])
    ->middleware('anima.capture');

// Route capture with provider tags
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('anima.capture:stripe,billing');

Route::post('/webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->middleware('anima.capture:github,vcs');
```

The middleware records:
- HTTP Method and Full URI
- Raw Request Headers
- Raw Request Payload (JSON or text)
- Response HTTP Status Code and Response Body
- Execution Duration in milliseconds
- Provider tags and synthetic replay flags

---

### 2. Bypassing Cryptographic Signatures During Replays

When an altered payload is replayed synthetically, external cryptographic signatures (such as Stripe or GitHub HMAC signatures) will naturally fail verification. Anima provides the `BypassesReplaySignatures` trait to safely permit synthetic replays in local and testing environments.

Use this trait inside your webhook verification middleware or controller:

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
        // Safely bypass signature checks during local or testing synthetic replays
        if ($this->isValidReplay($request)) {
            return $next($request);
        }

        // Standard HMAC signature verification
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

> **Security Note:** `isValidReplay()` strictly checks that the application environment is `local` or `testing`. It is hardcoded to return `false` in `production`, preventing header spoofing attacks.

---

### 3. Using the Anima Workbench

Navigate to `/anima` in your browser (or your configured `ANIMA_PATH`).

The Workbench UI provides:
1. **Event Feed (Left Pane):** Real-time list of captured webhooks with method badges, status codes, timestamps, and search filters.
2. **Payload Editor (Right Pane):** Full Monaco Editor instance with JSON validation, dark mode, and formatting tools.
3. **Header Inspector:** Add, update, remove, and reset request headers before dispatching.
4. **Synthesize & Replay:** One-click in-memory request execution with a slide-over panel detailing execution duration, status codes, and response bodies.

---

### 4. Swappable Storage Drivers

#### Isolated SQLite Driver (`sqlite`)
If you prefer not to create tables in your primary database, switch the driver to `sqlite`:

```env
ANIMA_STORAGE_DRIVER=sqlite
ANIMA_SQLITE_PATH=/path/to/storage/anima/anima.sqlite
```

Anima will automatically create the dedicated SQLite file, directory, and schema without requiring database migrations.

#### Redis Driver (`redis`)
For ephemeral storage with automatic TTL expiration:

```env
ANIMA_STORAGE_DRIVER=redis
ANIMA_REDIS_TTL=86400
```

---

## Docker Compose Testing Stack

A multi-service `docker-compose.yml` file is included for testing against multiple database backends:

```bash
# Start test services
docker compose up -d

# Stop test services
docker compose down
```

Services included:
- **Redis 7** on port `6379`
- **PostgreSQL 16** on port `5432`
- **MySQL 8.0** on port `3306`
- **MariaDB 11** on port `3307`

---

## Running the Test Suite

Run the PHPUnit test suite:

```bash
./vendor/bin/phpunit
```

Compile frontend assets:

```bash
npm install
npm run build
```

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
