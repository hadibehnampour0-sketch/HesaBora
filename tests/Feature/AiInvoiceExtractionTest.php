<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\AiInvoiceExtraction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AiVision\AiInvoiceExtractionService;
use App\Services\AiVision\Contracts\InvoiceVisionParserInterface;
use App\Services\AiVision\Parsers\DeterministicMockVisionParser;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiInvoiceExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected AiInvoiceExtractionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Bind deterministic mock parser for testing
        $this->app->bind(InvoiceVisionParserInterface::class, DeterministicMockVisionParser::class);

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Dakhl AI Vision Test Co',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'ai-invoices.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->service = app(AiInvoiceExtractionService::class);
    }

    public function test_ingest_paper_invoice_creates_pending_review_record(): void
    {
        $file = UploadedFile::fake()->create('scanned_receipt.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->user)->post(route('ai-invoices.store'), [
            'file' => $file,
        ]);

        $extraction = AiInvoiceExtraction::first();
        $this->assertNotNull($extraction);
        $this->assertSame('PENDING_REVIEW', $extraction->status);
        $this->assertGreaterThan(0, $extraction->subtotal);
        $this->assertGreaterThan(0, $extraction->vat_amount);
        $this->assertSame($extraction->subtotal + $extraction->vat_amount, $extraction->total_amount);
        $this->assertNotEmpty($extraction->raw_extracted_data['items']);

        $response->assertRedirect(route('ai-invoices.review', $extraction->id));
        Storage::disk('public')->assertExists($extraction->file_path);
    }

    public function test_auto_match_existing_vendor_by_phone(): void
    {
        // DeterministicMockVisionParser outputs phone '02188776655'
        $vendor = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'شرکت بازرگانی پارس آریا',
            'phone' => '02188776655',
            'balance' => 0,
        ]);

        $file = UploadedFile::fake()->create('invoice_vendor.jpg', 200, 'image/jpeg');
        $extraction = $this->service->ingest($file, $this->company->id, $this->user->id);

        $this->assertSame($vendor->id, $extraction->matched_customer_id);
    }

    public function test_human_verification_and_updating_line_items(): void
    {
        $file = UploadedFile::fake()->create('test_invoice.jpg', 200, 'image/jpeg');
        $extraction = $this->service->ingest($file, $this->company->id, $this->user->id);

        $updatedPayload = [
            'vendor_name' => 'فروشگاه لوازم التحریر البرز',
            'vendor_phone' => '09121112233',
            'vendor_national_code' => '1234567890',
            'invoice_number' => 'ALB-9988',
            'invoice_date' => '2026-10-20',
            'items' => [
                [
                    'name' => 'زونکن ۷ سانتی کتان',
                    'quantity' => 10,
                    'unit_price' => 120000,
                    'total_price' => 1200000,
                    'vat_amount' => 120000,
                    'discount' => 0,
                ],
            ],
            'subtotal' => 1200000,
            'vat_amount' => 120000,
            'total_amount' => 1320000,
        ];

        $response = $this->actingAs($this->user)->put(
            route('ai-invoices.update', $extraction->id),
            $updatedPayload
        );

        $response->assertRedirect(route('ai-invoices.review', $extraction->id));

        $fresh = $extraction->fresh();
        $this->assertSame('VERIFIED', $fresh->status);
        $this->assertSame('فروشگاه لوازم التحریر البرز', $fresh->vendor_name);
        $this->assertSame(1200000, $fresh->subtotal);
        $this->assertSame(1320000, $fresh->total_amount);
        $this->assertCount(1, $fresh->raw_extracted_data['items']);
    }

    public function test_convert_verified_extraction_to_draft_buy_invoice(): void
    {
        $file = UploadedFile::fake()->create('buy_invoice.jpg', 200, 'image/jpeg');
        $extraction = $this->service->ingest($file, $this->company->id, $this->user->id);

        // Convert to Buy Invoice
        $response = $this->actingAs($this->user)->post(route('ai-invoices.convert', $extraction->id));

        $freshExtraction = $extraction->fresh();
        $this->assertSame('CONVERTED', $freshExtraction->status);
        $this->assertNotNull($freshExtraction->converted_invoice_id);

        /** @var Invoice $invoice */
        $invoice = Invoice::find($freshExtraction->converted_invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceType::BUY, $invoice->invoice_type);
        $this->assertNull($invoice->document_id); // Respecting D-114 guardrail: draft first, never directly posted
        $this->assertSame($this->company->id, $invoice->company_id);
        $this->assertNotEmpty($invoice->items);

        $response->assertRedirect(route('invoices.show', $invoice->id));
    }

    public function test_reject_paper_invoice(): void
    {
        $file = UploadedFile::fake()->create('unreadable_receipt.jpg', 200, 'image/jpeg');
        $extraction = $this->service->ingest($file, $this->company->id, $this->user->id);

        $response = $this->actingAs($this->user)->post(
            route('ai-invoices.reject', $extraction->id),
            ['reason' => 'تصویر فاکتور به شدت تار و ناخواناست']
        );

        $response->assertRedirect(route('ai-invoices.index'));

        $fresh = $extraction->fresh();
        $this->assertSame('REJECTED', $fresh->status);
        $this->assertSame('تصویر فاکتور به شدت تار و ناخواناست', $fresh->rejection_reason);
    }

    public function test_cross_company_isolation(): void
    {
        $company2 = Company::create([
            'name' => 'Competitor Company',
            'fiscal_year' => 1405,
        ]);

        $extraction2 = AiInvoiceExtraction::create([
            'company_id' => $company2->id,
            'file_path' => 'ai_invoices/2/secret.jpg',
            'status' => 'PENDING_REVIEW',
            'vendor_name' => 'Secret Supplier Co',
            'subtotal' => 10000000,
            'vat_amount' => 1000000,
            'total_amount' => 11000000,
        ]);

        // Attempting to access from Company 1 context should abort with 404 (FiscalYearScope) or 403
        $response = $this->actingAs($this->user)->get(route('ai-invoices.review', $extraction2->id));
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }
}
