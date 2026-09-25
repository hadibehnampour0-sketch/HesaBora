<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/**
 * Phase U: the New Customer wizard is a pure presentation layer over the same
 * form fields/route/validation, shown only on `customers.create` in Simple mode.
 */
class CustomerWizardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);
        CustomerGroup::factory()->withSubject()->create(['name' => 'g', 'company_id' => $this->companyId]);

        $this->user = User::factory()->create();
        Company::find($this->companyId)->users()->syncWithoutDetaching($this->user);
        foreach (['customers.create', 'customers.store', 'customers.edit', 'customers.index'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
        $this->actingAs($this->user);
    }

    public function test_advanced_mode_keeps_the_single_page_form_with_tabs(): void
    {
        $this->get(route('customers.create'))
            ->assertOk()
            ->assertDontSee('wizardMode: true', false)
            ->assertSee("activeTab === 'financial'", false);
    }

    public function test_simple_mode_shows_the_three_step_wizard(): void
    {
        $this->user->update(['ui_mode' => 'simple']);

        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('wizardMode: true', false)
            ->assertSee('step === 1', false)
            ->assertSee('step === 2', false)
            ->assertSee('step === 3', false)
            ->assertSee(__('Financial & other information'))
            ->assertDontSee("activeTab === 'financial'", false);
    }

    public function test_the_wizard_still_contains_every_field_of_the_advanced_form(): void
    {
        $advanced = $this->get(route('customers.create'))->getContent();
        $this->user->update(['ui_mode' => 'simple']);
        $simple = $this->get(route('customers.create'))->getContent();

        foreach (['group_id', 'name', 'subject_code', 'personal_code', 'ecnmcs_code', 'type', 'mobile', 'phone', 'fax', 'email',
            'web_page', 'postal_code', 'address', 'acc_name_1', 'acc_no_1', 'acc_bank_1', 'acc_name_2', 'acc_no_2', 'acc_bank_2',
            'connector', 'responsible', 'desc'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $advanced, "advanced form lost {$field}");
            $this->assertStringContainsString('name="'.$field.'"', $simple, "wizard lost {$field}");
        }
    }

    public function test_the_edit_page_is_never_a_wizard(): void
    {
        $this->user->update(['ui_mode' => 'simple']);
        $group = CustomerGroup::first();
        $customer = Customer::factory()->withGroup($group)->withSubject()->create(['company_id' => $this->companyId]);

        $this->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertDontSee('wizardMode: true', false);
    }
}
