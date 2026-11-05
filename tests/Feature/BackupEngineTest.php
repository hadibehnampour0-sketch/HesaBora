<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\BackupEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BackupEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected BackupEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['name' => 'شرکت آزمایشی دخل']);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'backups.index']),
            Permission::firstOrCreate(['name' => 'backups.create']),
            Permission::firstOrCreate(['name' => 'backups.export']),
            Permission::firstOrCreate(['name' => 'backups.import']),
            Permission::firstOrCreate(['name' => 'backups.settings']),
            Permission::firstOrCreate(['name' => 'backups.full-export']),
            Permission::firstOrCreate(['name' => 'backups.full-restore']),
            Permission::firstOrCreate(['name' => 'backups.download'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);

        $this->engine = app(BackupEngineService::class);
    }

    public function test_can_create_full_system_backup_package(): void
    {
        $result = $this->engine->createFullBackup(['include_documents' => false]);

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.neptune', $result['filename']);
        $this->assertNotEmpty($result['hash']);
        $this->assertEquals('Neptune Accounting & ERP', $result['manifest']['system']);
    }

    public function test_can_list_stored_backups(): void
    {
        $created = $this->engine->createFullBackup(['include_documents' => false]);

        $list = $this->engine->listBackups();

        $this->assertNotEmpty($list);
        $filenames = array_column($list, 'filename');
        $this->assertContains($created['filename'], $filenames);
    }

    public function test_can_save_and_retrieve_backup_settings(): void
    {
        $this->engine->saveSettings([
            'auto_backup_on_exit' => true,
            'local_target_path' => 'D:\TestBackups',
            'telegram_enabled' => true,
            'telegram_chat_id' => '99887766',
        ]);

        $settings = $this->engine->getSettings();

        $this->assertTrue($settings['auto_backup_on_exit']);
        $this->assertEquals('D:\TestBackups', $settings['local_target_path']);
        $this->assertTrue($settings['telegram_enabled']);
        $this->assertEquals('99887766', $settings['telegram_chat_id']);
    }

    public function test_backup_dashboard_route_renders_successfully(): void
    {
        $response = $this->get(route('backups.index'));

        $response->assertStatus(200);
        $response->assertSee('مرکز مدیریت پشتیبان‌گیری');
        $response->assertSee('تهیه و دانلود فول‌بکاپ');
    }

    public function test_save_settings_route_persists_settings(): void
    {
        $response = $this->post(route('backups.settings'), [
            'auto_backup_on_exit' => '1',
            'local_target_path' => 'E:\SafeVault',
            'telegram_enabled' => '1',
            'telegram_bot_token' => 'bot-test-token',
            'telegram_chat_id' => '12345678',
            'include_documents' => '1',
        ]);

        $response->assertRedirect(route('backups.index'));

        $settings = $this->engine->getSettings();
        $this->assertEquals('E:\SafeVault', $settings['local_target_path']);
        $this->assertEquals('bot-test-token', $settings['telegram_bot_token']);
    }

    public function test_can_restore_from_backup_with_safety_snapshot(): void
    {
        $backup = $this->engine->createFullBackup(['include_documents' => false]);

        $restoreResult = $this->engine->restoreFullBackup($backup['path']);

        $this->assertTrue($restoreResult['success']);
        $this->assertNotEmpty($restoreResult['safety_snapshot']);
        $this->assertFileExists($restoreResult['safety_snapshot']);
    }
}
