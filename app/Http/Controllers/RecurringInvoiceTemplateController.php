<?php

namespace App\Http\Controllers;

use App\Enums\RecurringFrequency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RecurringInvoiceTemplate;
use App\Models\Service;
use App\Models\Warehouse;
use App\Services\RecurringOperationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecurringInvoiceTemplateController extends Controller
{
    public function __construct(private readonly RecurringOperationService $service) {}

    public function index(Request $request)
    {
        $templates = RecurringInvoiceTemplate::orderBy('next_run_date')->get();

        if ($request->expectsJson()) {
            return response()->json(['templates' => $templates]);
        }

        return view('recurring-invoice-templates.index', [
            'templates' => $templates,
            'customers' => Customer::orderBy('name')->limit(1000)->get(['id', 'name']),
            'customerNames' => Customer::pluck('name', 'id'),
            'products' => Product::orderBy('name')->limit(1000)->get(['id', 'name']),
            'services' => Service::orderBy('name')->limit(1000)->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        // The form offers one combined picker ("product:12" / "service:3"); split it into the stored pair.
        $request->merge(['items' => collect($request->input('items', []))->map(function ($item) {
            if (is_array($item) && ! empty($item['item']) && str_contains($item['item'], ':')) {
                [$item['itemable_type'], $item['itemable_id']] = explode(':', $item['item'], 2);
            }
            unset($item['item']);

            return $item;
        })->all()]);

        foreach (['start_date', 'end_date'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => jalaliInputToGregorian($request->input($field), $field)]);
            }
        }

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
            'items.*.itemable_type' => ['required', 'string', Rule::in(['product', 'service'])],
            'items.*.itemable_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'numeric', 'min:0'],
            'items.*.unit_discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = array_map(fn (array $item) => array_merge(['unit_discount' => 0, 'vat' => 0], array_filter($item, fn ($value) => $value !== null)), $validated['items']);

        $template = $this->service->create($validated, $items, $request->user());

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
