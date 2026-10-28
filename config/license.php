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
        'dakhl-root-2026' => env('DAKHL_LICENSE_PUBKEY_2026', 'b79f7b64c4e0f5a6f5e1ad74c2a6d1ee34a03d595472825cada05b46b4002e11'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Revoked Key IDs
    |--------------------------------------------------------------------------
    |
    | Any license token issued with a revoked key ID will be rejected immediately.
    |
    */
    'revoked_kids' => [
        // 'dakhl-compromised-key-example',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Active Key ID
    |--------------------------------------------------------------------------
    */
    'default_kid' => 'dakhl-root-2026',

    /*
    |--------------------------------------------------------------------------
    | Default Grace Period
    |--------------------------------------------------------------------------
    |
    | Number of days an expired license remains functional in grace period (D-125).
    |
    */
    'grace_period_days' => 14,
];
