<?php

namespace App\Services\Sync;

use App\Enums\InvoiceType;
use App\Enums\NumberingLeaseStatus;
use App\Models\DeviceInstallation;
use App\Models\NumberingLease;
use Illuminate\Support\Facades\DB;

/**
 * D-075 stage 2.7C-A/B -- the Numbering Lease Contract and its Reserve
 * Range API surface. See the `numbering_leases` migration for the full
 * reservation-vs-consumption contract; this service is the only writer of
 * that table.
 */
class NumberingLeaseService
{
    /**
     * D-075 decision 4: server-authoritative, atomic allocation. Locks the
     * highest `range_end` ever issued for this (company, invoice_type)
     * series -- across every device, not just this one -- so two devices
     * reserving concurrently always get disjoint ranges. Decision 5: a
     * fresh reservation is always a NEW lease; a device's own previous,
     * still-open lease is never extended or reused here.
     */
    public function reserveRange(DeviceInstallation $device, InvoiceType $invoiceType, int $size = 1000): NumberingLease
    {
        return DB::transaction(function () use ($device, $invoiceType, $size) {
            $lastRangeEnd = NumberingLease::where('company_id', $device->company_id)
                ->where('invoice_type', $invoiceType)
                ->lockForUpdate()
                ->max('range_end');

            $start = ((int) ($lastRangeEnd ?? 0)) + 1;
            $end = $start + $size - 1;

            return NumberingLease::create([
                'company_id' => $device->company_id,
                'invoice_type' => $invoiceType,
                'origin_device_id' => $device->id,
                'range_start' => $start,
                'range_end' => $end,
                'next_number' => $start,
                'status' => NumberingLeaseStatus::ACTIVE,
            ]);
        });
    }

    /**
     * D-075 decision 6: consumes exactly the next number from the
     * device's OWN active lease for this series, or returns null if none
     * is available (exhausted or never reserved) -- the caller (
     * SyncPilotService::applyFinalize()) turns a null into the explicit
     * "Numbering unavailable" outcome, never a provisional number.
     */
    public function consumeNext(DeviceInstallation $device, InvoiceType $invoiceType): ?int
    {
        return DB::transaction(function () use ($device, $invoiceType) {
            $lease = NumberingLease::where('company_id', $device->company_id)
                ->where('invoice_type', $invoiceType)
                ->where('origin_device_id', $device->id)
                ->where('status', NumberingLeaseStatus::ACTIVE)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($lease === null || $lease->next_number > $lease->range_end) {
                return null;
            }

            $number = $lease->next_number;
            $lease->next_number = $number + 1;
            if ($lease->next_number > $lease->range_end) {
                $lease->status = NumberingLeaseStatus::EXHAUSTED;
            }
            $lease->save();

            return $number;
        });
    }
}
