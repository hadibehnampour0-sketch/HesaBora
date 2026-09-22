<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DevicePairingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DevicePairingController extends Controller
{
    public function __construct(private readonly DevicePairingService $pairingService) {}

    public function pair(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->pairingService->pair($validated['code'], $validated['device_name'] ?? null);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'installation_uuid' => $result['installation']->installation_uuid,
            'device_key' => $result['device_key'],
            'company_id' => $result['installation']->company_id,
        ], 201);
    }

    public function ping(Request $request)
    {
        $installation = $request->attributes->get('device_installation');

        return response()->json([
            'status' => 'ok',
            'company_id' => $installation->company_id,
        ]);
    }
}
