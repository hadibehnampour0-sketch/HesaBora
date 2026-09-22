<?php

namespace Tests\Feature;

use App\Enums\RecurringFrequency;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\RecurringInvoiceTemplate;
use App\Models\User;
use App\Services\RecurringOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * P3.3 (Recurring Operations), Epic Pack 3. One generic template mechanism
 * covers both recurring SELL invoices and recurring BUY "expenses" -- this
 * app has no separate Expense entity.
 */
class RecurringInvoiceTemplateTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected Company $company;

    protected User $user;

    protected Customer $customer;

    protected Product $product;

    protected \App\Models\Warehouse $warehouse;

    protected RecurringOperationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);
        $this->warehouse = \App\Models\Warehouse::create(['company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN']);
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'quantity' => 1000,
            'average_cost' => 100,
        ]);
        \App\Models\WarehouseProductStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id],
            ['quantity' => 1000, 'average_cost' => 100]
        );

        $this->service = app(RecurringOperationService::class);
    }

    private function items(): array
    {
        return [[
            'itemable_type' => 'product',
            'itemable_id' => $this->product->id,
            'quantity' => 2,
            'unit' => 500,
            'unit_discount' => 0,
            'vat' => 0,
        ]];
    }

    public function test_a_due_template_generates_exactly_one_invoice_and_advances_next_run_date(): void
    {
        $template = $this->service->create([
            'name' => 'Monthly retainer',
            'invoice_type' => 2, // SELL
            'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value,
            'start_date' => now()->subDay()->toDateString(),
        ], $this->items(), $this->user);

        $invoice = $this->service->generateForTemplate($template, $this->user);

        $this->assertNotNull($invoice);
        $this->assertSame($template->id, $invoice->recurring_invoice_template_id);

        $fresh = $template->fresh();
        $this->assertNotNull($fresh->last_generated_at);
        $this->assertTrue($fresh->next_run_date->gt(Carbon::today()));
    }

    public function test_a_not_yet_due_template_generates_nothing(): void
    {
        $template = $this->service->create([
            'name' => 'Future retainer',
            'invoice_type' => 2,
            'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value,
            'start_date' => now()->addMonth()->toDateString(),
        ], $this->items(), $this->user);

        $invoice = $this->service->generateForTemplate($template, $this->user);

        $this->assertNull($invoice);
        $this->assertSame(0, Invoice::count());
    }

    /**
     * Duplicate-prevention guard: calling generateForTemplate() twice back
     * -to-back for the same due date must only ever create ONE invoice --
     * the second call sees next_run_date already advanced past "today" and
     * safely no-ops.
     */
    public function test_generating_the_same_template_twice_in_a_row_only_creates_one_invoice(): void
    {
        $template = $this->service->create([
            'name' => 'Monthly retainer',
            'invoice_type' => 2,
            'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value,
            'start_date' => now()->subDay()->toDateString(),
        ], $this->items(), $this->user);

        $first = $this->service->generateForTemplate($template, $this->user);
        $second = $this->service->generateForTemplate($template->fresh(), $this->user);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Invoice::count());
    }

    public function test_generate_due_processes_all_due_templates_across_company(): void
    {
        $dueTemplate = $this->service->create([
            'name' => 'Due', 'invoice_type' => 2, 'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value, 'start_date' => now()->subDay()->toDateString(),
        ], $this->items(), $this->user);
        $futureTemplate = $this->service->create([
            'name' => 'Future', 'invoice_type' => 2, 'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value, 'start_date' => now()->addMonth()->toDateString(),
        ], $this->items(), $this->user);

        $generated = $this->service->generateDue($this->user);

        $this->assertCount(1, $generated);
        $this->assertSame($dueTemplate->id, $generated->first()->recurring_invoice_template_id);
    }

    public function test_a_template_past_its_end_date_is_not_generated(): void
    {
        $template = $this->service->create([
            'name' => 'Expired', 'invoice_type' => 2, 'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ], $this->items(), $this->user);

        $invoice = $this->service->generateForTemplate($template, $this->user);

        $this->assertNull($invoice);
    }

    public function test_an_inactive_template_is_not_generated(): void
    {
        $template = $this->service->create([
            'name' => 'Paused', 'invoice_type' => 2, 'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
            'frequency' => RecurringFrequency::MONTHLY->value, 'start_date' => now()->subDay()->toDateString(),
        ], $this->items(), $this->user);
        $template->update(['is_active' => false]);

        $invoice = $this->service->generateForTemplate($template->fresh(), $this->user);

        $this->assertNull($invoice);
    }

    // --- Cross-company isolation -------------------------------------

    public function test_company_a_cannot_generate_now_for_company_bs_template(): void
    {
        $companyA = Company::create(['name' => 'Company A', 'fiscal_year' => 1401]);
        $companyB = Company::create(['name' => 'Company B', 'fiscal_year' => 1402]);

        $role = Role::firstOrCreate(['name' => 'Super-Admin']);
        $actorA = User::factory()->create();
        $actorA->companies()->syncWithoutDetaching([$companyA->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(0);
        $actorA->assignRole($role);
        $registrar->setPermissionsTeamId(null);

        $previous = config('active-company-id');
        config(['active-company-id' => $companyB->id]);
        $customerB = Customer::factory()->create();
        $templateB = $this->service->create([
            'name' => 'B template', 'invoice_type' => 2, 'customer_id' => $customerB->id,
            'frequency' => RecurringFrequency::MONTHLY->value, 'start_date' => now()->subDay()->toDateString(),
        ], $this->items(), $this->user);
        config(['active-company-id' => $previous]);

        $this->actingAs($actorA)
            ->withCookie('active-company-id', (string) $companyA->id)
            ->post(route('recurring-invoice-templates.generate', $templateB))
            ->assertNotFound();

        $this->assertSame(0, Invoice::withoutGlobalScopes()->where('recurring_invoice_template_id', $templateB->id)->count());
    }
}
