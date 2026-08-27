<?php

namespace Anima\Http\Middleware;

use Anima\Anima;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    /**
     * Handle an incoming request, blocking it unless Anima::check() authorizes it.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Anima::check($request)) {
            abort(403, 'Unauthorized access to Anima.');
        }

        return $next($request);
    }
}
