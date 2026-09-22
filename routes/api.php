<?php

use App\Http\Controllers\Api\AttendanceLogController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DevicePairingController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\NumberingController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\V1\AttendanceLogController as V1AttendanceLogController;
use App\Http\Controllers\Api\V1\CompanyController as V1CompanyController;
use App\Http\Controllers\Api\V1\DocumentController as V1DocumentController;
use App\Http\Controllers\Api\V1\EmployeeController as V1EmployeeController;
use App\Http\Controllers\Api\V1\WebhookController as V1WebhookController;
use Illuminate\Support\Facades\Route;

// D-071 decision 6: pairing itself needs no prior device credential (the
// activation code IS the credential, single-use and short-lived) -- every
// other sync endpoint added from 2.4 onward requires 'auth.device'.
// Workstream T: brute-forcing the 8-char code (36^8 keyspace) within its
// 15-minute TTL is not practically feasible over HTTP even unthrottled, but
// throttle:10,1 is added anyway for defense-in-depth, mirroring the same
// convention already applied to /login and /reset-password.
Route::post('devices/pair', [DevicePairingController::class, 'pair'])
    ->middleware('throttle:10,1')
    ->name('api.devices.pair');

Route::middleware(['auth.device'])->group(function () {
    Route::get('devices/ping', [DevicePairingController::class, 'ping'])->name('api.devices.ping');

    // D-071 stage 2.5: gated Customer/Product Sync Pilot only.
    Route::post('sync/push', [SyncController::class, 'push'])->name('api.sync.push');
    Route::get('sync/pull', [SyncController::class, 'pull'])->name('api.sync.pull');

    // D-075 stage 2.7C-B: Reserve Range API, Invoice only.
    Route::post('sync/numbering/reserve-range', [NumberingController::class, 'reserveRange'])->name('api.sync.numbering.reserve-range');
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('companies', [CompanyController::class, 'index'])
        ->middleware('check-permission:companies.index')
        ->name('api.companies.index');
});

Route::prefix('companies/{company}')->middleware(['auth:sanctum', 'api-company'])->group(function () {
    Route::post('attendance/logs', [AttendanceLogController::class, 'store'])
        ->middleware('check-permission:attendance.attendance-logs.store')
        ->name('api.attendance-logs.store');
    Route::get('attendance/logs', [AttendanceLogController::class, 'index'])
        ->middleware('check-permission:attendance.attendance-logs.index')
        ->name('api.attendance-logs.index');

    Route::get('employees', [EmployeeController::class, 'index'])
        ->middleware('check-permission:hr.employees.index')
        ->name('api.employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])
        ->middleware('check-permission:hr.employees.store')
        ->name('api.employees.store');

    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('check-permission:documents.store')
        ->name('api.documents.store');
    Route::get('documents/{documentId}', [DocumentController::class, 'show'])
        ->middleware('check-permission:documents.show')
        ->name('api.documents.show');
    Route::post('documents/{documentId}/files', [DocumentController::class, 'attachFile'])
        ->middleware('check-permission:documents.files.store')
        ->name('api.documents.files.store');
});

// P6 (API v1 Platform): a NEW, parallel namespace -- deliberately additive.
// The unversioned routes above are left untouched (native app/device
// clients depend on their exact URLs and response shapes today); v1
// re-exposes the same functional surface through thin adapter controllers
// (App\Http\Controllers\Api\V1\*) that delegate to the SAME legacy
// controllers/services and just re-shape the response into the standard
// {data, meta}/{message, errors} envelope (ApiResponds trait). Permission
// strings are reused verbatim from the unversioned routes -- a token
// already scoped for e.g. `companies.index` works on both versions, since
// scoping is a Spatie-permission concept, not a URL-version concept.
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('companies', [V1CompanyController::class, 'index'])
            ->middleware('check-permission:companies.index')
            ->name('companies.index');
    });

    Route::prefix('companies/{company}')->middleware(['auth:sanctum', 'api-company'])->group(function () {
        Route::post('attendance/logs', [V1AttendanceLogController::class, 'store'])
            ->middleware(['check-permission:attendance.attendance-logs.store', 'idempotency'])
            ->name('attendance-logs.store');
        Route::get('attendance/logs', [V1AttendanceLogController::class, 'index'])
            ->middleware('check-permission:attendance.attendance-logs.index')
            ->name('attendance-logs.index');

        Route::get('employees', [V1EmployeeController::class, 'index'])
            ->middleware('check-permission:hr.employees.index')
            ->name('employees.index');
        Route::post('employees', [V1EmployeeController::class, 'store'])
            ->middleware(['check-permission:hr.employees.store', 'idempotency'])
            ->name('employees.store');

        Route::post('documents', [V1DocumentController::class, 'store'])
            ->middleware(['check-permission:documents.store', 'idempotency'])
            ->name('documents.store');
        Route::get('documents/{documentId}', [V1DocumentController::class, 'show'])
            ->middleware('check-permission:documents.show')
            ->name('documents.show');
        Route::post('documents/{documentId}/files', [V1DocumentController::class, 'attachFile'])
            ->middleware(['check-permission:documents.files.store', 'idempotency'])
            ->name('documents.files.store');

        Route::get('webhooks', [V1WebhookController::class, 'index'])
            ->middleware('check-permission:webhooks.index')
            ->name('webhooks.index');
        Route::post('webhooks', [V1WebhookController::class, 'store'])
            ->middleware(['check-permission:webhooks.store', 'idempotency'])
            ->name('webhooks.store');
        Route::delete('webhooks/{webhook}', [V1WebhookController::class, 'destroy'])
            ->middleware('check-permission:webhooks.destroy')
            ->name('webhooks.destroy');
    });
});
