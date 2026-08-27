<?php

namespace Anima;

use Closure;
use Illuminate\Http\Request;

class Anima
{
    /**
     * The callback used to authorize access to the Anima dashboard and API.
     */
    public static ?Closure $authUsing = null;

    /**
     * Register a callback that determines whether a request may access Anima.
     */
    public static function auth(Closure $callback): void
    {
        static::$authUsing = $callback;
    }

    /**
     * Determine if the given request is authorized to access Anima.
     *
     * With no callback registered, access is only granted in environments
     * listed in `config('anima.allowed_environments')` (default: local/testing).
     */
    public static function check(Request $request): bool
    {
        if (static::$authUsing !== null) {
            return (bool) call_user_func(static::$authUsing, $request);
        }

        return app()->environment(
            ...(array) config('anima.allowed_environments', ['local', 'testing'])
        );
    }
}
