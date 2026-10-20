<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InvoicePrintTemplate;
use App\Models\User;
use App\Services\Template\InvoicePrintTemplateService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoicePrintTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected InvoicePrintTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Test Company',
            'fiscal_year' => 1405,
        ]);

        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'templates.*']));

        Cache::forever('active_company_id', $this->company->id);
        Cookie::queue('active-company-id', (string) $this->company->id);
        $_COOKIE['active-company-id'] = (string) $this->company->id;

        $this->service = app(InvoicePrintTemplateService::class);
    }

    public function test_default_templates_initialization(): void
    {
        $templates = $this->service->getTemplates($this->company->id);

        $this->assertNotEmpty($templates);
        $this->assertCount(2, $templates);

        $defaultA4 = $this->service->getDefaultTemplate($this->company->id, 'A4_PORTRAIT');
        $this->assertSame('A4_PORTRAIT', $defaultA4->paper_size);
        $this->assertTrue($defaultA4->is_default);

        $thermal = $this->service->getDefaultTemplate($this->company->id, 'THERMAL_80MM');
        $this->assertSame('THERMAL_80MM', $thermal->paper_size);
    }

    public function test_create_custom_template(): void
    {
        $response = $this->actingAs($this->user)->post(route('templates.store'), [
            'name' => 'قالب فاکتور رسمی بازرگانی',
            'paper_size' => 'A5_LANDSCAPE',
            'watermark_text' => 'نسخه حسابداری',
            'is_default' => 1,
            'header_config' => [
                'custom_title' => 'صورتحساب ویژه',
                'show_logo' => true,
            ],
            'columns_config' => [
                ['key' => 'row_num', 'label' => '#', 'visible' => true, 'width_percent' => 10],
                ['key' => 'description', 'label' => 'کالا', 'visible' => true, 'width_percent' => 60],
                ['key' => 'total_amount', 'label' => 'مبلغ کل', 'visible' => true, 'width_percent' => 30],
            ],
        ]);

        $response->assertRedirect(route('templates.index'));

        $this->assertDatabaseHas('invoice_print_templates', [
            'company_id' => $this->company->id,
            'name' => 'قالب فاکتور رسمی بازرگانی',
            'paper_size' => 'A5_LANDSCAPE',
            'watermark_text' => 'نسخه حسابداری',
            'is_default' => true,
        ]);
    }

    public function test_set_default_template(): void
    {
        $t1 = InvoicePrintTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب ۱',
            'paper_size' => 'A4_PORTRAIT',
            'is_default' => true,
        ]);

        $t2 = InvoicePrintTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب ۲',
            'paper_size' => 'A4_PORTRAIT',
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->post(route('templates.default', $t2->id));
        $response->assertRedirect(route('templates.index'));

        $t1->refresh();
        $t2->refresh();

        $this->assertFalse($t1->is_default);
        $this->assertTrue($t2->is_default);
    }

    public function test_duplicate_template(): void
    {
        $t = InvoicePrintTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب اصلی',
            'paper_size' => 'A4_PORTRAIT',
            'is_default' => true,
            'watermark_text' => 'اصل',
        ]);

        $response = $this->actingAs($this->user)->post(route('templates.duplicate', $t->id));
        $response->assertRedirect(route('templates.index'));

        $this->assertDatabaseHas('invoice_print_templates', [
            'company_id' => $this->company->id,
            'name' => 'قالب اصلی (کپی)',
            'watermark_text' => 'اصل',
            'is_default' => false,
        ]);
    }

    public function test_preview_invoice_html_rendering(): void
    {
        $template = InvoicePrintTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب آزمایشی',
            'paper_size' => 'A4_PORTRAIT',
            'watermark_text' => 'آزمایشی واتر',
            'header_config' => [
                'custom_title' => 'صورتحساب ویژه بازرگانی دخل',
                'show_company_name' => true,
            ],
            'columns_config' => InvoicePrintTemplate::defaultColumnsConfig(),
            'footer_config' => InvoicePrintTemplate::defaultFooterConfig(),
        ]);

        $response = $this->actingAs($this->user)->get(route('templates.preview', $template->id));
        $response->assertOk();
        $response->assertSee('صورتحساب ویژه بازرگانی دخل');
        $response->assertSee('آزمایشی واتر');
    }

    public function test_cross_company_template_isolation(): void
    {
        InvoicePrintTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'قالب شرکت اول',
            'paper_size' => 'A4_PORTRAIT',
        ]);

        $companyB = Company::factory()->create(['fiscal_year' => 1405]);
        InvoicePrintTemplate::create([
            'company_id' => $companyB->id,
            'name' => 'قالب شرکت دوم',
            'paper_size' => 'A4_PORTRAIT',
        ]);

        $response = $this->actingAs($this->user)->get(route('templates.index'));
        $response->assertOk();
        $response->assertSee('قالب شرکت اول');
        $response->assertDontSee('قالب شرکت دوم');
    }
}
