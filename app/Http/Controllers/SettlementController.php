<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\SettlementInstallment;
use App\Models\SettlementObligation;
use App\Services\PaymentService;
use App\Services\SettlementService;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function __construct(private readonly SettlementService $service, private readonly PaymentService $paymentService) {}

    public function storeForCheque(Cheque $cheque)
    {
        $obligation = $this->service->createObligationForCheque($cheque, request()->user());

        return redirect()->route('settlements.show', $obligation)
            ->with('success', __('Settlement obligation created successfully.'));
    }

    public function show(SettlementObligation $obligation)
    {
        $this->ensureOwnedByActiveCompany($obligation);
        $obligation->load('installments', 'source');

        return view('settlements.show', [
            'obligation' => $obligation,
            'remainingAmount' => $this->service->remainingAmount($obligation),
            'isSettled' => $this->service->isSettled($obligation),
            'settlementSubjects' => $this->paymentService->settlementSubjects(),
            'installments' => $obligation->installments->map(fn (SettlementInstallment $installment) => [
                'model' => $installment,
                'paid' => $this->service->installmentPaidAmount($installment),
                'remaining' => $this->service->installmentRemainingAmount($installment),
                'status' => $this->service->installmentStatus($installment),
            ]),
        ]);
    }

    public function storeInstallments(Request $request, SettlementObligation $obligation)
    {
        $this->ensureOwnedByActiveCompany($obligation);

        $validated = $request->validate([
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->service->createInstallmentPlan($obligation, $validated['installments']);

        return redirect()->route('settlements.show', $obligation)
            ->with('success', __('Installment plan created successfully.'));
    }

    public function recordPayment(Request $request, SettlementInstallment $installment)
    {
        abort_unless($installment->obligation?->company_id === getActiveCompany(), 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        $this->service->recordInstallmentPayment($request->user(), $installment, (float) $validated['amount'], $validated);

        return redirect()->route('settlements.show', $installment->obligation_id)
            ->with('success', __('Settlement payment recorded successfully.'));
    }

    /**
     * `SettlementObligation`/`SettlementInstallment` have no `FiscalYearScope`
     * -- a route-bound `{obligation}`/`{installment}` resolves regardless of
     * company. Found adversarially while building the permission matrix
     * (Workstream J/U). `storeForCheque()` is unaffected: `Cheque` IS
     * FiscalYearScope-protected, so its route binding already guarantees
     * company match.
     */
    private function ensureOwnedByActiveCompany(SettlementObligation $obligation): void
    {
        abort_unless($obligation->company_id === getActiveCompany(), 404);
    }
}
