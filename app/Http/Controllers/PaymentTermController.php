<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentTerm;
use App\Services\PaymentTermService;
use Illuminate\Http\Request;

class PaymentTermController extends Controller
{
    public function __construct(private readonly PaymentTermService $service) {}

    public function index(Request $request)
    {
        $terms = PaymentTerm::with('lines')->orderBy('code')->get();

        if ($request->expectsJson()) {
            return response()->json(['payment_terms' => $terms]);
        }

        return view('payment-terms.index', ['terms' => $terms]);
    }

    public function store(Request $request)
    {
        // The form always shows four instalment rows; blank ones are ignored.
        $request->merge(['lines' => collect($request->input('lines', []))
            ->filter(fn ($line) => filled($line['days_offset'] ?? null) || filled($line['percentage'] ?? null))
            ->values()->all()]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.days_offset' => ['required', 'integer', 'min:0'],
            'lines.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]);

        $term = $this->service->create($validated, $validated['lines']);

        return redirect()->back()->with('success', __('Payment term created successfully.'))->with('payment_term_id', $term->id);
    }

    public function update(Request $request, PaymentTerm $paymentTerm)
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'lines' => ['nullable', 'array', 'min:1'],
            'lines.*.days_offset' => ['required_with:lines', 'integer', 'min:0'],
            'lines.*.percentage' => ['required_with:lines', 'numeric', 'gt:0', 'max:100'],
        ]);

        $this->service->update($paymentTerm, $validated, $validated['lines'] ?? null);

        return redirect()->back()->with('success', __('Payment term updated successfully.'));
    }

    public function applyToInvoice(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_term_id' => ['required', 'integer', 'exists:payment_terms,id'],
        ]);

        $term = PaymentTerm::findOrFail($validated['payment_term_id']);
        $obligation = $this->service->applyToInvoice($invoice, $term, $request->user());

        return redirect()->route('settlements.show', $obligation)
            ->with('success', __('Payment term applied successfully.'));
    }
}
