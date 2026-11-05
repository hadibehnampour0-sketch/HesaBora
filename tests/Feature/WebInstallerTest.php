<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WebInstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockFile = storage_path('installed');
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
        parent::tearDown();
    }

    public function test_installer_wizard_page_is_accessible_when_not_installed(): void
    {
        $response = $this->get(route('install.index'));

        $response->assertStatus(200);
        $response->assertSee('راه‌اندازی اولیه سامانه مالی و اداری نپتون');
        $response->assertSee('بررسی سلامت سرور و پیش‌نیازها');
    }

    public function test_installer_seed_endpoint_seeds_baseline_permissions_and_config(): void
    {
        $response = $this->postJson(route('install.seed'));
        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_installer_setup_admin_creates_company_and_admin_and_locks_installer(): void
    {
        $payload = [
            'company_name' => 'شرکت حسابداری نپتون تست',
            'admin_name' => 'مدیر سیستم تست',
            'email' => 'test-admin@neptune.ir',
            'password' => 'SecurePass1405!',
            'password_confirmation' => 'SecurePass1405!',
        ];

        $response = $this->postJson(route('install.setup-admin'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'redirect' => route('login'),
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'شرکت حسابداری نپتون تست',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test-admin@neptune.ir',
            'name' => 'مدیر سیستم تست',
        ]);

        $this->assertFileExists($this->lockFile);

        // Subsequent visits must redirect to login
        $redirectResponse = $this->get(route('install.index'));
        $redirectResponse->assertRedirect(route('login'));

        // Subsequent API calls must be rejected
        $seedResponse = $this->postJson(route('install.seed'));
        $seedResponse->assertStatus(403);
    }
}
