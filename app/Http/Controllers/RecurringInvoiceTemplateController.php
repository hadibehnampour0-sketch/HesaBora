<?php

namespace App\Http\Controllers;

use App\Enums\RecurringFrequency;
use App\Models\RecurringInvoiceTemplate;
use App\Services\RecurringOperationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringInvoiceTemplateController extends Controller
{
    public function __construct(private readonly RecurringOperationService $service) {}

    public function index()
    {
        return response()->json(['templates' => RecurringInvoiceTemplate::orderBy('next_run_date')->get()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'invoice_type' => ['required', 'integer', Rule::in([1, 2])],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'frequency' => ['required', 'integer', Rule::enum(RecurringFrequency::class)],
            'interval' => ['nullable', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.itemable_type' => ['required', 'string'],
            'items.*.itemable_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'numeric', 'min:0'],
        ]);

        $template = $this->service->create($validated, $validated['items'], $request->user());

        return redirect()->back()->with('success', __('Recurring template created successfully.'))->with('template_id', $template->id);
    }

    public function generateNow(RecurringInvoiceTemplate $recurringInvoiceTemplate, Request $request)
    {
        $invoice = $this->service->generateForTemplate($recurringInvoiceTemplate, $request->user());

        if (! $invoice) {
            return redirect()->back()->with('error', __('This template is not due yet.'));
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('Invoice generated from recurring template.'));
    }
}
