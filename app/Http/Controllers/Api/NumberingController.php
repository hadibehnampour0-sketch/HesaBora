<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Services\Sync\NumberingLeaseService;
use Illuminate\Http\Request;

/**
 * D-075 stage 2.7C-B -- the Reserve Range API. A device calls this
 * explicitly, ahead of time, to obtain a disjoint block of official
 * numbers it may later consume one at a time via a FINALIZE sync
 * operation (SyncPilotService::applyFinalize()). Reservation never
 * consumes a number by itself (D-075 decision 6).
 */
class NumberingController extends Controller
{
    public function __construct(private readonly NumberingLeaseService $numberingLeaseService) {}

    public function reserveRange(Request $request)
    {
        $validated = $request->validate([
            'invoice_type' => ['required', 'string'],
            'size' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $invoiceType = InvoiceType::tryFromName($validated['invoice_type']);
        if ($invoiceType === null) {
            return response()->json(['message' => __('Invalid invoice_type.')], 422);
        }

        $device = $request->attributes->get('device_installation');

        $lease = $this->numberingLeaseService->reserveRange($device, $invoiceType, (int) ($validated['size'] ?? 1000));

        return response()->json([
            'lease_id' => $lease->id,
            'invoice_type' => $lease->invoice_type->valueName(),
            'range_start' => $lease->range_start,
            'range_end' => $lease->range_end,
            'next_number' => $lease->next_number,
            'status' => $lease->status->name,
        ]);
    }
}
