<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * P6 (API v1 Platform). The unversioned /api/* controllers (audited: no
 * base controller, no Resource layer, inconsistent ad hoc shapes -- some
 * use {data}, some {message, errors}, SyncController/NumberingController
 * use neither) are left untouched to avoid breaking the native app/device
 * clients that already depend on them. Every /api/v1/* controller instead
 * standardizes on this single envelope: {"data": ..., "meta": {...}?} for
 * success, {"message": ..., "errors": {...}?} for failure.
 */
trait ApiResponds
{
    protected function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function error(string $message, array $errors = [], int $status = 422): JsonResponse
    {
        $payload = ['message' => $message];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Re-shapes an existing unversioned Api controller's JsonResponse into
     * the v1 envelope, without duplicating that controller's own
     * validation/business logic.
     */
    protected function fromLegacy(JsonResponse $response): JsonResponse
    {
        $payload = $response->getData(true);
        $data = $payload['data'] ?? $payload;
        $meta = $payload['meta'] ?? [];

        return $this->success($data, $meta, $response->getStatusCode());
    }
}
