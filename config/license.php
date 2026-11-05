<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ed25519 Asymmetric Public Verification Keys
    |--------------------------------------------------------------------------
    |
    | Only the public verification keys are embedded in the shipped application.
    | Private signing keys NEVER reside in this repository or build artifacts.
    | Verification uses kid (key ID) to match the appropriate public key.
    |
    */
    'keys' => [
        // Neptune Primary Root Key (DEV & Default)
        'neptune-root-2026' => env('NEPTUNE_LICENSE_PUBKEY_2026', env('DAKHL_LICENSE_PUBKEY_2026', 'b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11')),

        // Legacy key compatibility
        'dakhl-root-2026' => env('DAKHL_LICENSE_PUBKEY_2026', 'b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11'),

        // Production Key Slot
        'neptune-prod-2026' => env('NEPTUNE_LICENSE_PUBKEY_PROD', env('DAKHL_LICENSE_PUBKEY_PROD', null)),
        'dakhl-prod-2026' => env('DAKHL_LICENSE_PUBKEY_PROD', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Revoked Key IDs
    |--------------------------------------------------------------------------
    */
    'revoked_kids' => [
        // 'compromised-key-example',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Active Key ID
    |--------------------------------------------------------------------------
    */
    'default_kid' => env('NEPTUNE_LICENSE_DEFAULT_KID', env('DAKHL_LICENSE_DEFAULT_KID', 'neptune-root-2026')),

    /*
    |--------------------------------------------------------------------------
    | Default Grace Period
    |--------------------------------------------------------------------------
    */
    'grace_period_days' => 14,
];
