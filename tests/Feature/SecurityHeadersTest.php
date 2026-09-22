<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Workstream T (Security Hardening): the app previously set none of the
 * standard defense-in-depth response headers. Most directly relevant to
 * this same workstream's stored-XSS finding (InvoiceStatusDecision) --
 * a Content-Security-Policy is a second, independent layer that would
 * still have constrained an XSS payload even if that specific escaping
 * bug had gone unnoticed.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_responses_include_the_standard_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('Strict-Transport-Security'); // not sent over plain HTTP

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
    }

    public function test_hsts_header_is_only_sent_over_https(): void
    {
        $response = $this->get('https://localhost/login', ['HTTPS' => 'on']);

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
