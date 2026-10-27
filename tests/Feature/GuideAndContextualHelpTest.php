<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuideAndContextualHelpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->user->companies()->syncWithoutDetaching($this->company);

        // Give home, documents.show, invoices.index to have business access
        Permission::firstOrCreate(['name' => 'home']);
        Permission::firstOrCreate(['name' => 'documents.show']);
        Permission::firstOrCreate(['name' => 'invoices.index']);
        $this->user->givePermissionTo(['home', 'documents.show', 'invoices.index']);
    }

    public function test_guest_cannot_view_guide(): void
    {
        $response = $this->get(route('guide.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_guide_overview(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('guide.index'));

        $response->assertOk();
        $response->assertSee('راهنمای کامل و مصور سامانه دخل');
        $response->assertSee('مفاهیم پایه و شروع به کار با دخل');
        $response->assertSee('نمودار جریان یکپارچه اطلاعات در دخل');
    }

    public function test_user_can_view_different_topics_in_guide(): void
    {
        $topics = [
            'sales' => 'چرخه فروش، فاکتور، صندوق POS و تهاتر',
            'treasury' => 'خزانه‌داری، نقدینگی و چرخه ۷ گانه چک صیادی',
            'warehouse' => 'انبارداری، ردیابی سریال و لات (بچ ساخت)',
            'accounting' => 'حسابداری دوبل، ساختار کدینگ و اسناد مالی',
            'tax' => 'سامانه مودیان و پایانه‌های فروشگاهی',
            'copilot' => 'دستیار مالی هوش مصنوعی دخل (AI Copilot)',
            'shortcuts' => 'کلیدهای میانبر و ابزارهای سریع صفحه‌کلید',
        ];

        foreach ($topics as $topic => $expectedText) {
            $response = $this->actingAs($this->user)
                ->withSession(['active_company_id' => $this->company->id])
                ->get(route('guide.index', ['topic' => $topic]));

            $response->assertOk();
            $response->assertSee($expectedText);
        }
    }

    public function test_invalid_topic_defaults_to_overview(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('guide.index', ['topic' => 'nonexistent_topic']));

        $response->assertOk();
        $response->assertSee('مفاهیم پایه و شروع به کار با دخل');
    }

    public function test_guide_links_are_present_in_navigation_menus(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_company_id' => $this->company->id])
            ->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('guide.index'));
    }
}
