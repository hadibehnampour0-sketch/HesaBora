<?php

namespace App\Services;

use App\Enums\DeviceInstallationStatus;
use App\Models\Company;
use App\Models\DeviceActivationCode;
use App\Models\DeviceInstallation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * D-071 decision 6: Pairing/Activation, independent of but connectable to
 * Licensing (D-048). Flow: a logged-in web user issues a short-lived
 * activation code for their company; a desktop installation redeems that
 * code once to receive its own installation_uuid + device_key (the key is
 * returned in plaintext exactly once -- only its hash is ever stored).
 * After pairing, the device authenticates via that credential, never by
 * re-deriving identity from anything else.
 */
class DevicePairingService
{
    private const CODE_LENGTH = 8;

    private const CODE_TTL_MINUTES = 15;

    public function issueActivationCode(Company $company, User $user): string
    {
        $code = strtoupper(Str::random(self::CODE_LENGTH));

        DeviceActivationCode::create([
            'code_hash' => hash('sha256', $code),
            'company_id' => $company->id,
            'created_by' => $user->id,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        return $code;
    }

    /**
     * Redeem an activation code, creating and pairing a new installation in
     * one step. Returns the plaintext device_key -- the ONLY time it is ever
     * available; only its hash is persisted.
     *
     * @return array{installation: DeviceInstallation, device_key: string}
     */
    public function pair(string $code, ?string $deviceName = null): array
    {
        return DB::transaction(function () use ($code, $deviceName) {
            $activationCode = DeviceActivationCode::where('code_hash', hash('sha256', strtoupper(trim($code))))
                ->lockForUpdate()
                ->first();

            if (! $activationCode || ! $activationCode->isUsable()) {
                throw ValidationException::withMessages([
                    'code' => [__('This activation code is invalid or has expired.')],
                ]);
            }

            $deviceKey = Str::random(40);

            $installation = DeviceInstallation::create([
                'installation_uuid' => (string) Str::uuid(),
                'device_key_hash' => Hash::make($deviceKey),
                'name' => $deviceName,
                'company_id' => $activationCode->company_id,
                // D-076: durably keep the pairing user -- otherwise this
                // fact only ever existed on the one-time activation code.
                'paired_by_user_id' => $activationCode->created_by,
                'status' => DeviceInstallationStatus::ACTIVE,
                'paired_at' => now(),
            ]);

            $activationCode->update([
                'used_at' => now(),
                'used_by_installation_id' => $installation->id,
            ]);

            return ['installation' => $installation, 'device_key' => $deviceKey];
        });
    }

    public function authenticate(string $installationUuid, string $deviceKey): ?DeviceInstallation
    {
        $installation = DeviceInstallation::where('installation_uuid', $installationUuid)->first();

        if (! $installation || ! $installation->isActive()) {
            return null;
        }

        if (! Hash::check($deviceKey, $installation->device_key_hash)) {
            return null;
        }

        $installation->update(['last_seen_at' => now()]);

        return $installation;
    }

    public function revoke(DeviceInstallation $installation): void
    {
        $installation->update(['status' => DeviceInstallationStatus::REVOKED]);
    }
}
