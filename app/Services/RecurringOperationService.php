<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\RecurringFrequency;
use App\Models\Invoice;
use App\Models\RecurringInvoiceTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P3.3 (Recurring Operations). One generic mechanism for both recurring
 * SELL invoices and recurring BUY "expenses" (this app has no separate
 * Expense entity -- a periodic vendor bill is structurally a recurring BUY
 * invoice). Duplicate-prevention guard: generateForTemplate() locks the
 * template row, re-checks next_run_date is still due INSIDE that lock,
 * and only advances next_run_date after the Invoice is actually created,
 * all in one transaction -- so a template already processed for its
 * current due date is never processed a second time, whether from two
 * overlapping scheduler runs or a manual "run now" racing the scheduler.
 */
class RecurringOperationService
{
    public function create(array $data, array $items, User $user): RecurringInvoiceTemplate
    {
        $this->validateItems($items);

        $startDate = Carbon::parse($data['start_date']);

        return RecurringInvoiceTemplate::create([
            'name' => $data['name'],
            'invoice_type' => $data['invoice_type'],
            'customer_id' => $data['customer_id'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'items' => $items,
            'frequency' => $data['frequency'],
            'interval' => $data['interval'] ?? 1,
            'start_date' => $startDate,
            'end_date' => $data['end_date'] ?? null,
            'next_run_date' => $startDate,
            'created_by' => $user->id,
        ]);
    }

    private function validateItems(array $items): void
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => [__('A recurring template must have at least one line item.')],
            ]);
        }
    }

    /**
     * Finds and generates every template due as of $asOf (defaults to
     * today), one at a time -- each generation is its own locked
     * transaction, so one template's failure never blocks another's.
     *
     * @return Collection<int, Invoice>
     */
    public function generateDue(User $user, ?Carbon $asOf = null): Collection
    {
        $asOf = $asOf ?? Carbon::today();

        $dueTemplateIds = RecurringInvoiceTemplate::query()
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', $asOf->toDateString())
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $asOf->toDateString()))
            ->pluck('id');

        return $dueTemplateIds->map(function (int $id) use ($user, $asOf) {
            $template = RecurringInvoiceTemplate::find($id);

            return $template ? $this->generateForTemplate($template, $user, $asOf) : null;
        })->filter()->values();
    }

    public function generateForTemplate(RecurringInvoiceTemplate $template, User $user, ?Carbon $asOf = null): ?Invoice
    {
        $asOf = $asOf ?? Carbon::today();

        return DB::transaction(function () use ($template, $user, $asOf) {
            $locked = RecurringInvoiceTemplate::query()->lockForUpdate()->findOrFail($template->id);

            if (! $locked->is_active || $locked->next_run_date->gt($asOf) || ($locked->end_date && $locked->end_date->lt($asOf))) {
                return null;
            }

            $number = (Invoice::withoutGlobalScopes()->max('number') ?? 0) + 1;

            $result = InvoiceService::createInvoice(
                $user,
                [
                    'title' => $locked->name,
                    'date' => $locked->next_run_date->toDateString(),
                    'invoice_type' => $locked->invoice_type,
                    'customer_id' => $locked->customer_id,
                    'warehouse_id' => $locked->warehouse_id,
                    'document_number' => $number,
                    'number' => $number,
                    'recurring_invoice_template_id' => $locked->id,
                ],
                $locked->items,
                true
            );

            $invoice = $result['invoice'];

            // createInvoice(..., approved: true) only fully posts a
            // Document immediately for BUY/return types -- a SELL invoice
            // is left at READY_TO_APPROVE and needs its own explicit
            // approval step (see WarehouseInvoiceStockTest/
            // DomainBusinessScenariosTest for the same two-step pattern).
            // A recurring template is meant to post a real invoice each
            // period, not leave it pending.
            if ($invoice->invoice_type === InvoiceType::SELL) {
                (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
            }

            $nextRunDate = $this->advance($locked->next_run_date->copy(), $locked->frequency, $locked->interval);

            $locked->update([
                'last_generated_at' => $asOf->toDateString(),
                'next_run_date' => $nextRunDate,
            ]);

            return $result['invoice'];
        });
    }

    private function advance(Carbon $date, RecurringFrequency $frequency, int $interval): Carbon
    {
        return $date->addMonths($frequency->months() * max(1, $interval));
    }
}
