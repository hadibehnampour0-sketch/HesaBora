<?php

namespace Tests\Feature;

use App\Enums\DeviceInstallationStatus;
use App\Models\Company;
use App\Models\DeviceInstallation;
use App\Models\User;
use App\Services\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * D-071 decision 6: Device/company identity via Pairing/Activation,
 * independent of but connectable to Licensing.
 */
class DevicePairingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected DevicePairingService $pairingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pairingService = app(DevicePairingService::class);
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'devices.*']));
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'devices.activation-codes.*']));
        $this->company->users()->syncWithoutDetaching($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
    }

    public function test_pairing_with_a_valid_code_creates_an_active_installation(): void
    {
        $code = $this->pairingService->issueActivationCode($this->company, $this->user);

        $result = $this->pairingService->pair($code, 'Reception PC');

        $this->assertSame(DeviceInstallationStatus::ACTIVE, $result['installation']->status);
        $this->assertSame($this->company->id, $result['installation']->company_id);
        $this->assertNotEmpty($result['device_key']);
        $this->assertNotSame($result['device_key'], $result['installation']->device_key_hash);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $code = $this->pairingService->issueActivationCode($this->company, $this->user);
        $this->pairingService->pair($code);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->pairingService->pair($code);
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $code = $this->pairingService->issueActivationCode($this->company, $this->user);
        \App\Models\DeviceActivationCode::query()->update(['expires_at' => now()->subMinute()]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->pairingService->pair($code);
    }

    public function test_an_unknown_code_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->pairingService->pair('NOTAREALCODE');
    }

    public function test_authenticate_succeeds_with_correct_credentials(): void
    {
        $code = $this->pairingService->issueActivationCode($this->company, $this->user);
        $result = $this->pairingService->pair($code);

        $authenticated = $this->pairingService->authenticate($result['installation']->installation_uuid, $result['device_key']);

        $this->assertNotNull($authenticated);
        $this->assertSame($result['installation']->id, $authenticated->id);
        $this->assertNotNull($authenticated->fresh()->last_seen_at);
    }

    public function test_authenticate_fails_with_wrong_device_key(): void
    {
        $code = $this->pairingService->issueActivationCode($this->company, $this->user);
        $result = $this->pairingService->pair($code);

        $authenticated = $this->pairingService->authenticate($result['installation']->installation_uuid, 'wrong-key');

        $this->assertNull($authenticated);
    }

    public function test_revoked_device_cannot_authenticate(): void
    {
        $code = $this->pairingService->issueActivationCode($this->company, $this->user);
        $result = $this->pairingService->pair($code);

        $this->pairingService->revoke($result['installation']);

        $authenticated = $this->pairingService->authenticate($result['installation']->installation_uuid, $result['device_key']);
        $this->assertNull($authenticated);
    }

    public function test_pairing_and_ping_work_end_to_end_over_http(): void
    {
        // Activation-code generation over HTTP is covered separately
        // (test_activation_code_route_redirects_with_a_success_message);
        // here we only need a real code to exercise the actual pairing/ping
        // API round-trip.
        $plainCode = $this->pairingService->issueActivationCode($this->company, $this->user);

        $pairResponse = $this->postJson(route('api.devices.pair'), [
            'code' => $plainCode,
            'device_name' => 'Test Device',
        ]);

        $pairResponse->assertCreated();
        $pairResponse->assertJsonStructure(['installation_uuid', 'device_key', 'company_id']);

        $pingResponse = $this->withHeaders([
            'X-Device-Uuid' => $pairResponse->json('installation_uuid'),
            'X-Device-Key' => $pairResponse->json('device_key'),
        ])->getJson(route('api.devices.ping'));

        $pingResponse->assertOk();
        $pingResponse->assertJson(['status' => 'ok', 'company_id' => $this->company->id]);
    }

    public function test_ping_without_credentials_is_rejected(): void
    {
        $this->getJson(route('api.devices.ping'))->assertUnauthorized();
    }

    public function test_repeated_pairing_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('api.devices.pair'), [
                'code' => 'WRONGCOD',
                'device_name' => 'Test Device',
            ])->assertStatus(422);
        }

        $this->postJson(route('api.devices.pair'), [
            'code' => 'WRONGCOD',
            'device_name' => 'Test Device',
        ])->assertStatus(429);
    }

    public function test_activation_code_route_redirects_with_a_success_message(): void
    {
        $this->post(route('devices.activation-codes.store'))
            ->assertRedirect(route('devices.index'));

        $this->assertSame(1, \App\Models\DeviceActivationCode::where('company_id', $this->company->id)->count());
    }

    public function test_devices_index_page_renders(): void
    {
        $this->get(route('devices.index'))->assertOk();
    }
}
