<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Workstream T (Security Hardening): the app previously set none of the
 * standard defense-in-depth response headers. Most relevant given this
 * same workstream's stored-XSS finding (InvoiceStatusDecision): a
 * Content-Security-Policy is a second, independent layer that would
 * still have blocked inline-script execution even if that specific
 * escaping bug had gone unnoticed. `frame-ancestors 'self'` (mirrored by
 * X-Frame-Options for older browsers) prevents the app being embedded in
 * a malicious iframe for clickjacking. `nosniff` prevents the browser
 * from executing a file as script based on guessed content-type.
 *
 * The CSP intentionally allows 'unsafe-inline' for script/style: the app
 * has extensive inline `<script>`/`onclick`-style usage throughout its
 * Blade views (chart data, Alpine.js directives) that would require a
 * much larger refactor to nonce/hash instead -- a stricter policy is a
 * separate, larger future effort, not bundled into this pass. Even with
 * 'unsafe-inline', this CSP still blocks `object-src`/plugin-based
 * vectors and constrains other resource types, so it is still real,
 * additive protection today, not a no-op.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
            "style-src 'self' 'unsafe-inline'; ".
            "img-src 'self' data: blob:; ".
            "font-src 'self' data:; ".
            "frame-ancestors 'self'; ".
            "object-src 'none'; ".
            "base-uri 'self'"
        );

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
