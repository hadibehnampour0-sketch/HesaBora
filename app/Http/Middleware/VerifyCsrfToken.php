<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // External platform webhooks (Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md):
        // a real platform never has a Dakhl session/CSRF token; the URL token + HMAC signature
        // the adapter itself verifies are its authentication instead.
        'integrations/*/webhook/*',
    ];
}
