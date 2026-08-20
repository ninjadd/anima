<?php

namespace Anima\Traits;

use Illuminate\Http\Request;

trait BypassesReplaySignatures
{
    /**
     * Determine if the incoming request is a valid synthetic replay that should bypass cryptographic signature checks.
     *
     * This method will ONLY return true if:
     * 1. The X-Anima-Replay: true header is present on the request.
     * 2. The application is running in a non-production safe environment ('local' or 'testing').
     *
     * In production, this method strictly returns false regardless of headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function isValidReplay(Request $request): bool
    {
        // Absolute production safety guard
        if (app()->isProduction()) {
            return false;
        }

        // Only allow in local or testing environments
        if (! app()->environment('local', 'testing')) {
            return false;
        }

        $headerValue = strtolower((string) $request->header('X-Anima-Replay', ''));

        return $headerValue === 'true' || $headerValue === '1';
    }

    /**
     * Alias helper method for determining if request is an authorized Anima replay.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function isAnimaReplay(Request $request): bool
    {
        return $this->isValidReplay($request);
    }
}
