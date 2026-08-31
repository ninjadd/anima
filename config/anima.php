<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Anima Master Switch & Environment Constraints
    |--------------------------------------------------------------------------
    |
    | Enable or disable Anima webhook interception and synthetic request replay.
    |
    */
    'enabled' => env('ANIMA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Anima Route Path & Prefix
    |--------------------------------------------------------------------------
    |
    | The default URI path for the Anima dashboard and API endpoints.
    |
    */
    'path' => env('ANIMA_PATH', 'anima'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware that should be assigned to the Anima routes.
    |
    */
    'middleware' => [
        'web',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | The Anima dashboard and API are safe-by-default: unless you register a
    | custom authorization callback via `Anima::auth(Closure $callback)` in
    | your AppServiceProvider, access is only granted when the application is
    | running in one of the environments listed here.
    |
    */
    'allowed_environments' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Redacted Headers
    |--------------------------------------------------------------------------
    |
    | Header names (case-insensitive) whose values should be replaced with
    | "[REDACTED]" before a captured entry is persisted. Extend this list with
    | any additional auth/signature headers your application uses.
    |
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
    |
    | When true, `/anima/api/replay` will only dispatch synthetic requests to
    | routes tagged with the `anima.capture` middleware, preventing the
    | endpoint from being used to forge requests against arbitrary routes.
    |
    */
    'replay' => [
        'restrict_to_captured_routes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Throttle settings ("max attempts,decay minutes", per Laravel's throttle
    | middleware syntax) applied to the purge and replay endpoints.
    |
    */
    'rate_limits' => [
        'replay' => '30,1',
        'purge' => '10,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Anima supports swappable storage drivers for persisting intercepted
    | webhooks and synthetic replay payloads.
    |
    | Supported drivers: "database", "sqlite", "redis"
    |
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

        // Note: filtered queries (search/tag/status) against the Redis driver
        // scan the entire index and degrade linearly with entry count, since
        // Redis has no secondary index for these fields. For large capture
        // histories with heavy filtered querying, prefer "database"/"sqlite".
        'redis' => [
            'connection' => env('ANIMA_REDIS_CONNECTION', 'default'),
            'prefix' => env('ANIMA_REDIS_PREFIX', 'anima:entries'),
            'ttl' => env('ANIMA_REDIS_TTL', 86400),
        ],
    ],
];
