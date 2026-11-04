<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\UpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UpdateEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected UpdateService $updateService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'updates.index']),
            Permission::firstOrCreate(['name' => 'updates.check']),
            Permission::firstOrCreate(['name' => 'updates.apply'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);

        $this->updateService = app(UpdateService::class);
    }

    public function test_can_check_for_updates(): void
    {
        $info = $this->updateService->checkForUpdates();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('current_version', $info);
        $this->assertArrayHasKey('latest_version', $info);
        $this->assertArrayHasKey('changelog', $info);
        $this->assertArrayHasKey('features', $info['changelog']);
    }

    public function test_can_apply_update_with_safety_snapshot(): void
    {
        $result = $this->updateService->applyUpdate();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['safety_snapshot']);
        $this->assertFileExists($result['safety_snapshot']);
    }

    public function test_updates_index_route_renders_successfully(): void
    {
        $response = $this->get(route('updates.index'));

        $response->assertStatus(200);
        $response->assertSee('مدیریت نسخه‌ها و بروزرسانی خودکار نرم‌افزار');
    }

    public function test_updates_check_route_returns_json(): void
    {
        $response = $this->post(route('updates.check'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'has_update',
            'current_version',
            'latest_version',
            'changelog',
        ]);
    }

    public function test_updates_apply_route_redirects_with_success(): void
    {
        $response = $this->post(route('updates.apply'));

        $response->assertRedirect(route('updates.index'));
        $response->assertSessionHas('success');
    }
}
