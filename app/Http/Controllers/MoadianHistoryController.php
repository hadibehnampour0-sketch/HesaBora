<?php

namespace App\Http\Controllers;

use App\Enums\MoadianStatus;
use App\Models\Invoice;
use App\Models\MoadianHistory;
use App\Services\MoadianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoadianHistoryController extends Controller
{
    public function __construct(private readonly MoadianService $moadianService) {}

    public function index(Request $request): View
    {
        // CRITICAL (P9 hardening pass): MoadianHistory has no
        // FiscalYearScope of its own -- without this, every company's tax
        // submission history (tax IDs, customer economic codes potentially
        // present in the `data` blob) was visible here regardless of which
        // company was active. `whereHas('invoice')` leverages Invoice's own
        // scope, the only company-deriving relation this table has, same
        // pattern as guardrail #36's WarehouseProductStock fix.
        $query = MoadianHistory::whereHas('invoice')->with('invoice');

        if ($request->filled('status')) {
            $query->where('data->status', $request->input('status'));
        }

        if ($request->filled('invoice_number')) {
            $query->whereHas('invoice', fn ($invoice) => $invoice->where('number', $request->input('invoice_number')));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $moadianHistories = $query->latest()->paginate(10)->withQueryString();

        $latestHistoryIds = MoadianHistory::whereIn('invoice_id', $moadianHistories->pluck('invoice_id')->unique())
            ->selectRaw('MAX(id) as id')
            ->groupBy('invoice_id')
            ->pluck('id')
            ->all();

        return view('moadian-histories.index', compact('moadianHistories', 'latestHistoryIds'));
    }

    public function show(Request $request, Invoice $invoice): View
    {
        // P12.1: MAX(id), not created_at -- see Invoice::latestMoadianHistory().
        $latestHistory = $invoice->moadianHistories()->latest('id')->first();
        $latestHistoryStatus = $latestHistory
            ? (MoadianStatus::fromData($latestHistory->data['status'] ?? null) ?? MoadianStatus::UNKNOWN)->value
            : null;

        $query = MoadianHistory::with('invoice')->where('invoice_id', $invoice->id);

        if ($request->filled('status')) {
            $query->where('data->status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $moadianHistories = $query->latest()->paginate(10)->withQueryString();

        return view('moadian-histories.show', compact('moadianHistories', 'invoice', 'latestHistoryStatus'));
    }

    public function checkStatus(Request $request, Invoice $invoice): RedirectResponse|JsonResponse
    {
        $latestHistory = $invoice->moadianHistories()->latest('id')->first();

        if (! $latestHistory || ! isset($latestHistory->data['referenceNumber'])) {
            $message = __('No reference number available to check status.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : redirect()->back()->with('error', $message);
        }

        $statusData = $this->moadianService->moadianStatus($latestHistory->data['referenceNumber'], $invoice);
        $status = MoadianStatus::fromData($statusData['status'] ?? null) ?? MoadianStatus::UNKNOWN;

        if ($status->isFailed()) {
            $message = __('Failed to check status from Moadian. Please try again.');

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'status' => $status->value], 422)
                : redirect()->back()->with('error', $message);
        }

        $message = __('Status checked and updated successfully.');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'status' => $status->value, 'status_label' => $status->label(), 'status_color' => $status->color()])
            : redirect()->back()->with('success', $message);
    }
}
