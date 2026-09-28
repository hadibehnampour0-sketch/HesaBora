<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\View\View;

/**
 * T-7 (docs/project-context/Dakhl_TAX_MODULE_COMPLETENESS_PLAN_2026-09-25.md): every approved
 * sales invoice that has never successfully reached Moadian, in one screen, with its latest
 * attempt's status/reason and a resend button (reuses the existing, unchanged
 * `invoices.send-moadian` route/`MoadianService::sendInvoice()` -- no new send logic here).
 */
class MoadianWorkQueueController extends Controller
{
    public function index(): View
    {
        $invoices = Invoice::whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::VOID])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereDoesntHave('moadianHistories', fn ($q) => $q->where('data->status', 'SUCCESS'))
            ->with(['customer', 'latestMoadianHistory'])
            ->orderByDesc('date')
            ->paginate(20);

        return view('moadian-histories.queue', compact('invoices'));
    }
}
