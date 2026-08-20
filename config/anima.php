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

        'redis' => [
            'connection' => env('ANIMA_REDIS_CONNECTION', 'default'),
            'prefix' => env('ANIMA_REDIS_PREFIX', 'anima:entries'),
            'ttl' => env('ANIMA_REDIS_TTL', 86400),
        ],
    ],
];
