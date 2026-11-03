<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Accounting Engine Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "cleanroom", "legacy", "shadow"
    |
    | cleanroom: Routes domain actions through the PSR-12 clean-room engine
    |            (Dakhl\Core) with strict mathematical invariants & ADR-A..E guards.
    | legacy:    Routes through legacy transaction builders and services.
    | shadow:    Executes legacy and clean-room in parallel, comparing outputs.
    |
    */
    'driver' => env('ACCOUNTING_DRIVER', 'cleanroom'),

    /*
    |--------------------------------------------------------------------------
    | Domain-Specific Overrides
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'accounts' => env('ACCOUNTING_ACCOUNTS_DRIVER', 'cleanroom'),
        'numbering' => env('ACCOUNTING_NUMBERING_DRIVER', 'cleanroom'),
        'posting' => env('ACCOUNTING_POSTING_DRIVER', 'cleanroom'),
        'reversals' => env('ACCOUNTING_REVERSALS_DRIVER', 'cleanroom'),
        'periods' => env('ACCOUNTING_PERIODS_DRIVER', 'cleanroom'),
        'invoices' => env('ACCOUNTING_INVOICES_DRIVER', 'cleanroom'),
        'inventory' => env('ACCOUNTING_INVENTORY_DRIVER', 'cleanroom'),
        'ancillary_costs' => env('ACCOUNTING_ANCILLARY_DRIVER', 'cleanroom'),
        'treasury' => env('ACCOUNTING_TREASURY_DRIVER', 'cleanroom'),
        'allocations' => env('ACCOUNTING_ALLOCATIONS_DRIVER', 'cleanroom'),
        'cheques' => env('ACCOUNTING_CHEQUES_DRIVER', 'cleanroom'),
        'fixed_assets' => env('ACCOUNTING_FIXED_ASSETS_DRIVER', 'cleanroom'),
        'cost_chain' => env('ACCOUNTING_COST_CHAIN_DRIVER', 'cleanroom'),
        'provenance' => env('ACCOUNTING_PROVENANCE_DRIVER', 'cleanroom'),
        'closing' => env('ACCOUNTING_CLOSING_DRIVER', 'cleanroom'),
    ],
];
