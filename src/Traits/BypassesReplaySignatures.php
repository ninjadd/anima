<?php

namespace Anima\Traits;

use Illuminate\Http\Request;

trait BypassesReplaySignatures
{
    /**
     * Determine if the incoming request is a valid synthetic replay that should bypass cryptographic signature checks.
     *
     * This method will ONLY return true if:
     * 1. The request carries the `anima_synthetic_replay` attribute, set internally by
     *    KernelRequestSynthesizer when it dispatches a replay through the kernel.
     * 2. The application is running in a non-production safe environment ('local' or 'testing').
     *
     * In production, this method strictly returns false regardless of the attribute.
     *
     * The trust signal is a request *attribute*, not a header: attributes can only be set
     * by code running inside this process, whereas headers on a real inbound request are
     * fully attacker-controlled (e.g. a local dev server exposed via an ngrok tunnel). Do
     * not change this to inspect a header — doing so reintroduces an unauthenticated
     * signature-verification bypass reachable by anyone who can reach the endpoint.
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

        return $request->attributes->get('anima_synthetic_replay') === true;
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
