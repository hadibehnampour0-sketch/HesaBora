<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePrintTemplate;
use App\Models\Product;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SystemSmokeAndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'شرکت آزمایشی دخل',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        // Grant super admin and all permissions
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'customers.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'products.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'pos.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'sms.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'loyalty.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'telephony.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'inquiries.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'financing.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'templates.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'ai-invoices.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'migration.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
    }

    public function test_pos_terminal_view_and_checkout_flow(): void
    {
        $product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'روغن زیتون فرابکر ۱ لیتری',
            'code' => '5001',
            'barcode' => '6261234567890',
            'selling_price' => 450000,
            'average_cost' => 380000,
            'quantity' => 20,
            'vat' => 0,
        ]);

        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'مشتری حضوری فروشگاه',
            'mobile' => '09121110000',
            'balance' => 0,
        ]);

        // 1. Visit terminal page
        $res = $this->actingAs($this->user)->get(route('pos.terminal'));
        $res->assertOk()
            ->assertSee('صندوق فروشگاهی هوشمند (POS)');

        // 2. Scan barcode
        $scanRes = $this->actingAs($this->user)->postJson(route('pos.scan'), [
            'barcode' => '6261234567890',
        ]);
        $scanRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('item.product_id', $product->id);

        // 3. Checkout
        $checkoutRes = $this->actingAs($this->user)->postJson(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 450000],
            ],
            'customer_id' => $customer->id,
            'terminal_id' => 'POS-COUNTER-1',
            'operator_name' => 'صندوقدار شماره ۱',
        ]);
        $checkoutRes->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_sms_automation_dashboard_and_test_send(): void
    {
        $res = $this->actingAs($this->user)->get(route('sms.index'));
        $res->assertOk()
            ->assertSee('SMS Automation & Notifications');

        $configRes = $this->actingAs($this->user)->post(route('sms.config.update'), [
            'provider' => 'kavenegar',
            'api_key' => 'test-api-key-1234',
            'sender_number' => '10008888',
            'auto_send_invoices' => '1',
            'auto_send_payments' => '1',
            'auto_send_cheques' => '1',
        ]);
        $configRes->assertRedirect(route('sms.index'));
    }

    public function test_competitor_migration_screens_and_preview(): void
    {
        $res = $this->actingAs($this->user)->get(route('migration.index'));
        $res->assertOk()
            ->assertSee('مهاجرت هوشمند از نرم‌افزارهای حسابداری رقیب');

        $previewRes = $this->actingAs($this->user)->post(route('migration.preview'), [
            'source_type' => 'holoo',
            'raw_json' => json_encode([
                'customers' => [['name' => 'شرکت آریا', 'balance' => 5000000]],
                'products' => [['name' => 'کالای نمونه', 'stock' => 10, 'cost' => 100000]],
                'cheques' => [],
            ]),
        ]);
        $previewRes->assertOk()
            ->assertSee('مرحله ۲: گزارش عیب‌یابی و تراز افتتاحیه');
    }

    public function test_official_inquiries_dashboard_and_validations(): void
    {
        $res = $this->actingAs($this->user)->get(route('inquiries.index'));
        $res->assertOk()
            ->assertSee('سامانه استعلام‌های هویتی و بانکی (شاهکار، شبا، کارت)');

        // Test card to sheba with valid Luhn checksum
        $inquiryRes = $this->actingAs($this->user)->postJson(route('inquiries.card'), [
            'card_number' => '6037991122334455',
        ]);
        $inquiryRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank_name', 'بانک ملی ایران');
    }

    public function test_customer_loyalty_and_wallet_screens(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'رضا رضایی',
            'mobile' => '09122223344',
            'balance' => 0,
        ]);

        $res = $this->actingAs($this->user)->get(route('loyalty.index'));
        $res->assertOk()
            ->assertSee('باشگاه مشتریان و کیف پول اعتباری (وفاداری)');

        // Customer wallet view
        $walletRes = $this->actingAs($this->user)->get(route('loyalty.customers.wallet', $customer->id));
        $walletRes->assertOk()
            ->assertSee('کیف پول و امتیازات مشتری: رضا رضایی');
    }

    public function test_financing_and_installments_workflow(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'خریدار اقساطی',
            'mobile' => '09123334455',
            'balance' => 0,
        ]);

        $res = $this->actingAs($this->user)->get(route('financing.index'));
        $res->assertOk()
            ->assertSee('فروش اقساطی، تسهیلات و محاسبه سود و جریمه دیرکرد');

        $createRes = $this->actingAs($this->user)->get(route('financing.create'));
        $createRes->assertOk()
            ->assertSee('ثبت قرارداد فروش اقساطی جدید');

        // Calculate schedule endpoint
        $calcRes = $this->actingAs($this->user)->postJson(route('financing.calculate'), [
            'principal' => 8000000,
            'tenor_months' => 4,
            'method' => 'MARKET_MONTHLY',
            'monthly_rate' => 3.5,
            'start_date' => '2026-10-20',
        ]);
        $calcRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenor_months', 4)
            ->assertJsonPath('data.schedule.0.installment_number', 1);
    }

    public function test_template_designer_and_preview(): void
    {
        $customer = Customer::create([
            'company_id' => $this->company->id,
            'name' => 'مشتری رسمی قالب',
            'balance' => 0,
        ]);

        $template = InvoicePrintTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب رسمی شرکت تجاری',
            'paper_size' => 'A4_PORTRAIT',
            'is_default' => true,
        ]);

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'number' => 2001,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'amount' => 3500000,
            'vat' => 350000,
            'final_amount' => 3850000,
            'date' => '2026-10-20',
        ]);

        $res = $this->actingAs($this->user)->get(route('templates.index'));
        $res->assertOk()
            ->assertSee('طراح بصری قالب‌های چاپ فاکتور');

        $previewRes = $this->actingAs($this->user)->get(route('templates.preview', [
            'template' => $template->id,
            'invoice_id' => $invoice->id,
        ]));
        $previewRes->assertOk()
            ->assertSee('صورتحساب فروش')
            ->assertSee('3,500,000 ریال');
    }

    public function test_telephony_caller_id_lookup_and_logs(): void
    {
        $res = $this->actingAs($this->user)->get(route('telephony.index'));
        $res->assertOk()
            ->assertSee('سیستم کالر آیدی (Caller ID) و تلفن ابری/سانترال VoIP');

        $lookupRes = $this->actingAs($this->user)->getJson(route('telephony.lookup', ['number' => '09129998877']));
        $lookupRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.found', false);
    }

    public function test_ai_paper_invoice_screens(): void
    {
        $res = $this->actingAs($this->user)->get(route('ai-invoices.index'));
        $res->assertOk()
            ->assertSee('ورود اطلاعات اسنادی با هوش مصنوعی و OCR فاکتورهای کاغذی');

        $uploadRes = $this->actingAs($this->user)->get(route('ai-invoices.upload'));
        $uploadRes->assertOk()
            ->assertSee('بارگذاری تصویر یا فایل PDF فاکتور خرید کاغذی');
    }
}
