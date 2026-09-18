<?php

namespace Anima;

use Anima\Contracts\PayloadStorageInterface;
use Anima\Contracts\RequestSynthesizerInterface;
use Anima\Http\Controllers\AssetController;
use Anima\Http\Middleware\Authorize;
use Anima\Http\Middleware\CaptureWebhook;
use Anima\Managers\StorageManager;
use Anima\Services\KernelRequestSynthesizer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AnimaServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/config/anima.php',
            'anima'
        );

        $this->app->singleton(StorageManager::class, function ($app) {
            return new StorageManager($app);
        });

        $this->app->singleton(PayloadStorageInterface::class, function ($app) {
            return $app->make(StorageManager::class)->driver();
        });

        $this->app->singleton(RequestSynthesizerInterface::class, function ($app) {
            return new KernelRequestSynthesizer(
                $app->make(\Illuminate\Contracts\Http\Kernel::class),
                $app
            );
        });
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('anima.capture', CaptureWebhook::class);
        $this->app['router']->aliasMiddleware('anima.authorize', Authorize::class);

        $this->loadViewsFrom(dirname(__DIR__) . '/resources/views', 'anima');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__) . '/config/anima.php' => config_path('anima.php'),
            ], 'anima-config');

            $this->publishes([
                dirname(__DIR__) . '/resources/dist' => public_path('vendor/anima'),
            ], 'anima-assets');

            $this->publishes([
                dirname(__DIR__) . '/resources/views' => resource_path('views/vendor/anima'),
            ], 'anima-views');

            $timestamp = date('Y_m_d_His');
            $this->publishes([
                dirname(__DIR__) . '/database/migrations/create_anima_entries_table.php.stub' => database_path("migrations/{$timestamp}_create_anima_entries_table.php"),
            ], 'anima-migrations');
        }
    }

    /**
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        if (! config('anima.enabled', true)) {
            return;
        }

        $path = config('anima.path', 'anima');
        $middleware = array_merge(
            (array) config('anima.middleware', ['web']),
            ['anima.authorize']
        );

        // Assets are static, unauthenticated build output. Serve them without
        // the web middleware group (no session/CSRF/cookie overhead) and
        // without anima.authorize, so a custom Anima::auth() callback never
        // needs to run for a plain JS/CSS request.
        Route::get("{$path}/assets/{asset}", [AssetController::class, 'show'])
            ->where('asset', '.*')
            ->name('anima.assets');

        Route::group([
            'prefix' => "{$path}/api",
            'middleware' => $middleware,
        ], function () {
            $this->loadRoutesFrom(dirname(__DIR__) . '/routes/api.php');
        });

        Route::group([
            'prefix' => $path,
            'middleware' => $middleware,
        ], function () {
            $this->loadRoutesFrom(dirname(__DIR__) . '/routes/web.php');
        });
    }
}
