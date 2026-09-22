<?php

namespace App\Http\Controllers;

use App\Models\TaxAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * P12.2 (Tax Alert & Smart Notification Engine). Route-model binding on
 * `TaxAlert` is what enforces cross-company isolation here -- the model's
 * own `FiscalYearScope` (App\Models\TaxAlert::booted()) makes an alert
 * belonging to another company 404 before it ever reaches these methods,
 * the same mechanism every other company-scoped resource controller in
 * this app already relies on.
 */
class TaxAlertController extends Controller
{
    public function index(): JsonResponse
    {
        $alerts = TaxAlert::latest()->limit(20)->get();

        return response()->json([
            'unread_count' => TaxAlert::where('is_read', false)->count(),
            'alerts' => $alerts->map(fn (TaxAlert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'title' => $alert->title,
                'message' => $alert->message,
                'is_read' => $alert->is_read,
                'created_at' => $alert->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function read(TaxAlert $tax_alert): RedirectResponse|JsonResponse
    {
        $tax_alert->markRead();

        return request()->expectsJson()
            ? response()->json(['message' => __('Alert marked as read.')])
            : redirect()->back();
    }

    public function readAll(): RedirectResponse|JsonResponse
    {
        TaxAlert::where('is_read', false)->get()->each->markRead();

        return request()->expectsJson()
            ? response()->json(['message' => __('All alerts marked as read.')])
            : redirect()->back();
    }
}
