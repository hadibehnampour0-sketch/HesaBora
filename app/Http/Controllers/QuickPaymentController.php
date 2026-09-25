<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Services\AgeingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase U: "Collect money" / "Pay suppliers" as one guided page. It is a PRESENTATION
 * layer only -- it lists the open balances the ageing report already computes and
 * submits to the EXISTING `invoices.payments.store` route (PaymentController ->
 * PaymentService::createPayment), so validation, posting, locking and the D-084
 * immutability rules are exactly those of the invoice page's payment dialog.
 */
class QuickPaymentController extends Controller
{
    public function __construct(
        private readonly AgeingService $ageing,
        private readonly PaymentService $payments,
    ) {}

    public function create(Request $request, string $direction): View
    {
        abort_unless(in_array($direction, ['receive', 'pay'], true), 404);
        abort_unless($request->user()->can('invoices.payments.store'), 403);

        $type = $direction === 'receive' ? InvoiceType::SELL : InvoiceType::BUY;
        $report = $this->ageing->report($type);

        $parties = collect($report['parties'])->map(fn (array $party) => [
            'id' => $party['customer_id'],
            'name' => $party['name'],
            'total' => $party['total'],
            'invoices' => collect($party['invoices'])->map(fn (array $invoice) => [
                'id' => $invoice['id'],
                'number' => $invoice['number'],
                'date' => formatDate($invoice['date']),
                'age_days' => $invoice['age_days'],
                'remaining' => $invoice['remaining'],
            ])->values()->all(),
        ])->values()->all();

        $accounts = $this->payments->settlementSubjects()
            ->groupBy(fn ($subject) => $subject->parent?->name ?? __('Other'))
            ->map(fn ($group, $label) => [
                'label' => (string) $label,
                'options' => $group->map(fn ($subject) => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->formattedCode(),
                ])->values()->all(),
            ])->values()->all();

        return view('quick-payment.create', [
            'direction' => $direction,
            'parties' => $parties,
            'accounts' => $accounts,
            'total' => $report['total'],
        ]);
    }
}
