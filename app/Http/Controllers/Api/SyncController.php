<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncPilotService;
use Illuminate\Http\Request;

/**
 * D-071 stage 2.5: the gated Customer/Product Sync Pilot's push/pull API.
 * Both routes require `auth.device` -- the device identity is read from
 * `$request->attributes->get('device_installation')`, never from anything
 * the request body claims.
 */
class SyncController extends Controller
{
    public function __construct(private readonly SyncPilotService $syncPilotService) {}

    public function push(Request $request)
    {
        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1', 'max:200'],
            'entries.*.operation_id' => ['required', 'uuid'],
            'entries.*.entity_type' => ['required', 'string'],
            'entries.*.entity_uuid' => ['required', 'uuid'],
            'entries.*.operation' => ['required', 'integer', 'in:1,2,3,4,5'],
            // 'present' rather than 'required': a FINALIZE operation (D-075)
            // carries no payload of its own -- an empty array is valid.
            'entries.*.payload' => ['present', 'array'],
            'entries.*.base_sync_version' => ['nullable', 'integer'],
        ]);

        $device = $request->attributes->get('device_installation');

        $results = array_map(
            fn (array $entry) => $this->syncPilotService->pushEntry($device, $entry),
            $validated['entries']
        );

        return response()->json(['results' => $results]);
    }

    public function pull(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string'],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $device = $request->attributes->get('device_installation');

        $rows = $this->syncPilotService->pull(
            $device,
            $validated['entity_type'],
            (int) ($validated['after_id'] ?? 0),
            (int) ($validated['limit'] ?? 100)
        );

        return response()->json(['rows' => $rows]);
    }
}
