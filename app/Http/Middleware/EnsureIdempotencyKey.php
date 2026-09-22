<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P6 (API v1 Platform). An optional `Idempotency-Key` request header on a
 * v1 write route (POST/PUT/PATCH/DELETE) makes a retried request safe:
 * the SAME key + the SAME request body replays the original response
 * instead of re-executing the write a second time; the SAME key with a
 * DIFFERENT body is rejected as a conflict rather than silently accepted.
 *
 * Correctness relies entirely on the DB-level unique constraint on
 * (key, user_id) -- the claim-row INSERT is what actually serializes two
 * genuinely concurrent requests carrying the same key (see
 * IdempotencyKeyRaceTest), not any lock taken here. Only one request can
 * ever win that INSERT; every other request -- whether it arrives a
 * millisecond later or truly concurrently -- lands in the catch branch
 * below and never reaches $next($request), so the underlying write can
 * never be executed twice for the same key.
 *
 * This is a distinct concept from this app's other "idempotent" patterns
 * (guardrail #37's cross-reference): SyncPilotService's operation_id is a
 * domain-specific offline-sync replay key, and Payroll/PersonnelRequest's
 * exhaustive transition tables are a different mechanism entirely. This
 * middleware is the generic HTTP Idempotency-Key header pattern, scoped
 * only to the new /api/v1 namespace.
 */
class EnsureIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $user = $request->user();
        $routeSignature = $request->method().' '.$request->route()->getName();
        $fingerprint = hash('sha256', $routeSignature.'|'.json_encode($request->all()));

        try {
            $claim = IdempotencyKey::create([
                'key' => $key,
                'user_id' => $user->id,
                'company_id' => $request->route('company'),
                'route_signature' => $routeSignature,
                'request_fingerprint' => $fingerprint,
                'response_status' => null,
                'response_body' => null,
            ]);
        } catch (QueryException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_keys_key_user_unique')
                && ! str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }

            $existing = IdempotencyKey::where('key', $key)->where('user_id', $user->id)->first();

            if (! $existing || $existing->request_fingerprint !== $fingerprint) {
                return response()->json([
                    'message' => __('This Idempotency-Key was already used with a different request.'),
                ], 409);
            }

            if ($existing->response_status === null) {
                return response()->json([
                    'message' => __('A request with this Idempotency-Key is already being processed.'),
                ], 409);
            }

            return response()->json(
                json_decode($existing->response_body, true) ?? [],
                $existing->response_status
            );
        }

        $response = $next($request);

        $claim->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
        ]);

        return $response;
    }
}
