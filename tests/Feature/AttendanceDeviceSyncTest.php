<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceDeviceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceDeviceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Employee $employee1;
    protected Employee $employee2;
    protected AttendanceDeviceSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::firstOrCreate(['id' => 1], [
            'name' => 'Dakhl Attendance Test Co',
            'fiscal_year' => 1405,
        ]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching($this->user);

        Cache::forever('active_company_id', $this->company->id);
        config(['active-company-id' => $this->company->id]);

        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.index']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.store']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.sync']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.ping']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.simulate']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.devices.destroy']));

        $shift = WorkShift::factory()->create(['company_id' => $this->company->id]);

        $this->employee1 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'device_id' => '101',
            'work_shift_id' => $shift->id,
        ]);

        $this->employee2 = Employee::factory()->create([
            'company_id' => $this->company->id,
            'device_id' => '102',
            'work_shift_id' => $shift->id,
        ]);

        $this->syncService = app(AttendanceDeviceSyncService::class);
    }

    public function test_user_can_view_devices_index(): void
    {
        $this->actingAs($this->user);

        AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه درب ورودی',
            'brand' => 'zkteco',
            'protocol' => 'zk_tcp',
            'ip_address' => '192.168.1.201',
            'port' => 4370,
            'is_active' => true,
        ]);

        $response = $this->get(route('attendance.devices.index'));
        $response->assertStatus(200);
        $response->assertSee('دستگاه درب ورودی');
        $response->assertSee('192.168.1.201:4370');
        $response->assertSee('سینک اطلاعات از دستگاه');
    }

    public function test_user_can_register_new_attendance_device(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('attendance.devices.store'), [
            'name' => 'دستگاه سوله مونتاژ',
            'brand' => 'kara2000',
            'protocol' => 'zk_tcp',
            'ip_address' => '192.168.1.205',
            'port' => 4370,
            'comm_key' => '0',
            'location' => 'سالن شماره ۳',
            'auto_sync' => true,
        ]);

        $response->assertRedirect(route('attendance.devices.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_devices', [
            'company_id' => $this->company->id,
            'name' => 'دستگاه سوله مونتاژ',
            'brand' => 'kara2000',
            'ip_address' => '192.168.1.205',
        ]);
    }

    public function test_ping_device_updates_status(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه تست',
            'brand' => 'zkteco',
            'protocol' => 'mock',
            'ip_address' => 'mock',
            'port' => 4370,
            'is_active' => true,
        ]);

        $result = $this->syncService->pingDevice($device, true);
        $this->assertTrue($result['success']);

        $device->refresh();
        $this->assertEquals('online', $device->last_status);
        $this->assertNotNull($device->last_status_message);
    }

    public function test_direct_sync_fetches_punches_and_creates_attendance_logs(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه مرکزی',
            'brand' => 'zkteco',
            'protocol' => 'mock',
            'ip_address' => 'mock',
            'port' => 4370,
            'is_active' => true,
        ]);

        $result = $this->syncService->syncDevice($device, null, null, 'replace', true);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(2, $result['imported']);

        // Check AttendanceLog created for employee 101
        $log1 = AttendanceLog::where('employee_id', $this->employee1->id)->first();
        $this->assertNotNull($log1);
        $this->assertEquals(now()->toDateString(), $log1->log_date->toDateString());
        $this->assertNotNull($log1->entry_time);
        $this->assertNotNull($log1->exit_time);

        // Check AttendanceLog created for employee 102
        $log2 = AttendanceLog::where('employee_id', $this->employee2->id)->first();
        $this->assertNotNull($log2);

        $device->refresh();
        $this->assertEquals('synced', $device->last_status);
        $this->assertNotNull($device->last_sync_at);
    }

    public function test_adms_push_webhook_endpoint_processes_raw_punches(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه پوش',
            'brand' => 'zkteco',
            'protocol' => 'adms_push',
            'ip_address' => '192.168.1.100',
            'port' => 4370,
            'cloud_push_token' => 'test_push_token_123',
            'is_active' => true,
        ]);

        $today = now()->toDateString();
        $rawAttlog = "101\t{$today} 08:00:00\t0\t1\n101\t{$today} 17:00:00\t1\t1\n";

        $response = $this->call(
            'POST',
            route('api.attendance.iclock', ['token' => 'test_push_token_123', 'table' => 'ATTLOG']),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $rawAttlog
        );

        $response->assertStatus(200);
        $this->assertStringContainsString('OK: 1', $response->getContent());

        $log = AttendanceLog::where('employee_id', $this->employee1->id)->where('log_date', $today)->first();
        $this->assertNotNull($log);
        $this->assertEquals('08:00:00', $log->entry_time);
        $this->assertEquals('17:00:00', $log->exit_time);
    }

    public function test_unknown_pins_are_handled_gracefully(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه مهمان',
            'brand' => 'zkteco',
            'protocol' => 'zk_tcp',
            'ip_address' => '192.168.1.200',
            'port' => 4370,
            'is_active' => true,
        ]);

        // Punches with unregistered PIN 999
        $punches = [
            ['device_id' => '999', 'timestamp' => now()->toDateString() . ' 08:10:00', 'log_type' => 0],
        ];

        $result = $this->syncService->processRawPunches($punches, $this->company->id);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertContains('999', $result['unknown_pins']);
    }

    public function test_devices_are_scoped_by_company(): void
    {
        $otherCompany = Company::factory()->create();

        $deviceCo1 = AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه شرکت ۱',
            'brand' => 'zkteco',
            'protocol' => 'zk_tcp',
            'ip_address' => '192.168.1.1',
            'port' => 4370,
        ]);

        $deviceCo2 = AttendanceDevice::create([
            'company_id' => $otherCompany->id,
            'name' => 'دستگاه شرکت ۲',
            'brand' => 'zkteco',
            'protocol' => 'zk_tcp',
            'ip_address' => '192.168.1.2',
            'port' => 4370,
        ]);

        $this->actingAs($this->user);
        $response = $this->get(route('attendance.devices.index'));

        $response->assertSee('دستگاه شرکت ۱');
        $response->assertDontSee('دستگاه شرکت ۲');
    }

    public function test_sync_calculates_worked_minutes_and_delays(): void
    {
        $device = AttendanceDevice::create([
            'company_id' => $this->company->id,
            'name' => 'دستگاه اداری',
            'brand' => 'zkteco',
            'protocol' => 'zk_tcp',
            'ip_address' => '192.168.1.50',
            'port' => 4370,
            'is_active' => true,
        ]);

        $today = '2026-10-20';
        $punches = [
            ['device_id' => '101', 'timestamp' => "{$today} 08:30:00", 'log_type' => 0],
            ['device_id' => '101', 'timestamp' => "{$today} 16:30:00", 'log_type' => 1],
        ];

        $result = $this->syncService->processRawPunches($punches, $this->company->id);
        $this->assertEquals(1, $result['imported']);

        $log = AttendanceLog::where('employee_id', $this->employee1->id)->where('log_date', $today)->first();
        $this->assertNotNull($log);
        $this->assertEquals('08:30:00', $log->entry_time);
        $this->assertEquals('16:30:00', $log->exit_time);
        $this->assertGreaterThan(0, $log->worked);
    }
}
