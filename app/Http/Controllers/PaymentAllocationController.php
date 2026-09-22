<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentAllocationController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function storeCreditPayment(Request $request, Customer $customer)
    {
        $request->merge([
            'amount' => convertToFloat($request->input('amount', 0)),
            'date' => $request->input('date') ? convertToGregorian($request->input('date')) : null,
        ]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:20'],
        ]);

        $payment = $this->paymentService->createCreditPayment($request->user(), $customer, $validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Advance payment recorded successfully.'))
            ->with('payment_id', $payment->id);
    }

    public function allocate(Request $request, Payment $payment)
    {
        $this->ensureOwnedByActiveCompany($payment);

        $request->merge([
            'amount' => convertToFloat($request->input('amount', 0)),
        ]);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);

        $this->paymentService->allocatePayment(
            $request->user(),
            $payment,
            $invoice,
            (float) $validated['amount'],
            $validated['reference'] ?? null
        );

        return redirect()->back()->with('success', __('Payment allocated to invoice successfully.'));
    }

    public function destroy(Payment $payment, PaymentAllocation $allocation)
    {
        $this->ensureOwnedByActiveCompany($payment);
        abort_unless($allocation->payment_id === $payment->id, 404);

        $this->paymentService->removeAllocation($allocation);

        return redirect()->back()->with('success', __('Allocation removed successfully.'));
    }

    /**
     * `Payment` has no `company_id`/`FiscalYearScope` of its own -- a route-
     * bound `{payment}` here is NOT nested under an already-scoped
     * `{invoice}` the way `PaymentController`'s routes are, so nothing else
     * guarantees it belongs to the active company. See
     * Payment::resolvedCompanyId()'s docblock for the discovery context.
     */
    private function ensureOwnedByActiveCompany(Payment $payment): void
    {
        abort_unless($payment->resolvedCompanyId() === getActiveCompany(), 404);
    }
}
