<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Services\AgeingService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    /**
     * One amount from one party, spread over their open invoices oldest-first. Every slice goes
     * through PaymentService::createPayment (same validation/posting as the invoice dialog);
     * all slices commit together or not at all.
     */
    public function store(Request $request, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['receive', 'pay'], true), 404);
        abort_unless($request->user()->can('invoices.payments.store'), 403);

        $request->merge([
            'amount' => convertToFloat($request->input('amount', 0)),
            'date' => $request->input('date') ? convertToGregorian($request->input('date')) : null,
        ]);

        $validated = $request->validate([
            'party_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:20'],
        ]);

        $type = $direction === 'receive' ? InvoiceType::SELL : InvoiceType::BUY;
        $party = collect($this->ageing->report($type)['parties'])->firstWhere('customer_id', (int) $validated['party_id']);
        abort_unless($party, 404);

        if ($validated['amount'] > round((float) $party['total'], 2) + 0.005) {
            throw ValidationException::withMessages(['amount' => [__('The amount is more than the open balance.')]]);
        }

        $count = 0;

        try {
            DB::transaction(function () use ($request, $party, $validated, &$count) {
                $left = (float) $validated['amount'];

                foreach ($party['invoices'] as $row) {
                    if ($left <= 0.004) {
                        break;
                    }

                    $slice = round(min($left, (float) $row['remaining']), 2);
                    if ($slice <= 0) {
                        continue;
                    }

                    $decision = $this->payments->createPayment($request->user(), Invoice::findOrFail($row['id']), [...$validated, 'amount' => $slice]);

                    if ($decision->hasErrors()) {
                        throw new \RuntimeException(implode(' ', $decision->messages->pluck('text')->all()));
                    }

                    $left = round($left - $slice, 2);
                    $count++;
                }
            });
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', [$e->getMessage()]);
        }

        return redirect()->route('quick-payment.create', $direction)
            ->with('success', trans_choice(':count invoice settled.|:count invoices settled.', $count, ['count' => $count]));
    }
}
