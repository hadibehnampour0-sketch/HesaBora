<?php

namespace App\Http\Middleware;

use App\Services\DevicePairingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * D-071 decision 6: after pairing, a device authenticates via its own
 * stable credential (installation_uuid + device_key) -- it never re-derives
 * "who am I" from anything else at sync time.
 */
class AuthenticateDevice
{
    public function __construct(private readonly DevicePairingService $pairingService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $installationUuid = $request->header('X-Device-Uuid');
        $deviceKey = $request->header('X-Device-Key');

        if (! $installationUuid || ! $deviceKey) {
            return response()->json([
                'message' => __('Device credentials are required.'),
            ], 401);
        }

        $installation = $this->pairingService->authenticate($installationUuid, $deviceKey);

        if (! $installation) {
            return response()->json([
                'message' => __('Invalid or revoked device credentials.'),
            ], 401);
        }

        config(['active-company-id' => $installation->company_id]);
        $request->attributes->set('device_installation', $installation);

        return $next($request);
    }
}
