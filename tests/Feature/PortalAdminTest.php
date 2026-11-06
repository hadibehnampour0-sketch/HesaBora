<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PortalLead;
use App\Models\PortalOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PortalAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        Permission::firstOrCreate(['name' => 'access-super-admin-panel', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'configs.index', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super-Admin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole($role);
        $this->admin->givePermissionTo(['access-super-admin-panel', 'configs.index']);
    }

    public function test_super_admin_can_access_portal_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('management.portal.index'));
        $response->assertStatus(200);
        $response->assertSee('مرکز فرماندهی فروش، سرنخ‌ها و لایسنس‌های نپتون');
    }

    public function test_trial_request_creates_portal_lead(): void
    {
        $payload = [
            'customer_name' => 'فروشگاه نمونه افق',
            'phone' => '09121112233',
            'business_type' => 'سوپرمارکت',
        ];

        $response = $this->postJson(route('trial.request'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('portal_leads', [
            'customer_name' => 'فروشگاه نمونه افق',
            'phone' => '09121112233',
            'business_type' => 'سوپرمارکت',
            'status' => 'new',
        ]);
    }

    public function test_order_submit_creates_portal_order(): void
    {
        $payload = [
            'plan' => 'commercial',
            'customer_name' => 'شرکت توسعه تجارت پارس',
            'phone' => '09124445566',
            'company_name' => 'پارس هلدینگ',
            'extra_companies' => 2,
            'extra_users' => 3,
        ];

        $response = $this->postJson(route('order.submit'), $payload);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('portal_orders', [
            'customer_name' => 'شرکت توسعه تجارت پارس',
            'phone' => '09124445566',
            'plan' => 'commercial',
            'status' => 'pending',
            'extra_companies' => 2,
            'extra_users' => 3,
        ]);
    }

    public function test_super_admin_can_issue_order_license(): void
    {
        $order = PortalOrder::create([
            'order_reference' => 'DK-ORD-TEST-1234',
            'plan' => 'distribution',
            'customer_name' => 'پخش مویرگی شیراز',
            'phone' => '09171112233',
            'total_price' => 35000000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->post(route('management.portal.orders.issue-license', $order->id));
        $response->assertRedirect();
        $response->assertSessionHas('generated_key');

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertNotEmpty($order->issued_license_key);
    }

    public function test_super_admin_can_issue_custom_license(): void
    {
        $payload = [
            'customer_name' => 'شرکت صنعتی آذرخش',
            'edition' => 'manufacturing',
            'hardware_fingerprint' => '*',
            'max_companies' => 5,
            'max_users' => 10,
            'duration_months' => 0, // lifetime
        ];

        $response = $this->actingAs($this->admin)->post(route('management.portal.issue-custom-license'), $payload);
        $response->assertRedirect();
        $response->assertSessionHas('generated_key');
    }
}
