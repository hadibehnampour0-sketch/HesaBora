<?php

namespace Tests\Feature;

use App\DTO\InvoiceStatusDecision;
use App\Models\Product;
use Tests\TestCase;

/**
 * Workstream T (Security Hardening). `InvoiceStatusDecision::
 * toDetailText()`'s output is rendered via `{!! !!}` (unescaped) in
 * `resources/views/invoices/show.blade.php` through the
 * `<x-show-messages>` component -- deliberately, since its own error/
 * warning messages are pre-formatted with `<span class="...">` markup.
 * But `formatConflicts()`'s 'product' branch interpolates the conflicting
 * Product's raw `name` attribute directly into that same HTML string via
 * `sprintf()`, with no escaping.
 *
 * `StoreProductRequest`/`UpdateProductRequest` restrict `name` to
 * `^[\w\d\s\-\:\.]*$` (no `<`, `>`, `"`, `'`), which incidentally
 * prevents this on the normal web form -- but `ProductImportService`
 * (CSV import) applies no such restriction, only `trim()`. A CSV file
 * (from a compromised or malicious internal source: a bookkeeper,
 * consultant, or supply-chain-compromised spreadsheet) can set a
 * Product's name to arbitrary HTML/JavaScript, which then executes in
 * ANY other staff member's browser the next time an invoice
 * status-change conflict involving that product is displayed -- a real
 * stored XSS with a plausible internal-actor threat model (session/
 * cookie theft, CSRF-token exfiltration, or silent actions performed as
 * the victim).
 */
class InvoiceStatusDecisionXssTest extends TestCase
{
    public function test_a_malicious_product_name_is_escaped_in_the_rendered_conflict_detail_text(): void
    {
        $maliciousProduct = new Product([
            'name' => '<script>alert(document.cookie)</script>',
            'code' => 1,
        ]);

        $decision = new InvoiceStatusDecision;
        $decision->addMessage('error', 'insufficient_quantity_for_product');
        $decision->addConflict($maliciousProduct);

        $html = $decision->toDetailText();

        $this->assertStringNotContainsString('<script>alert(document.cookie)</script>', $html, 'a raw <script> tag from a product name must never appear unescaped in HTML rendered via {!! !!}');
        $this->assertStringContainsString('&lt;script&gt;', $html, 'the malicious markup must be HTML-entity-escaped, not silently dropped, so the product name is still visible as text');
    }
}
