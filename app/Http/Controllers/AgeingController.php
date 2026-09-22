<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Services\AgeingService;
use Illuminate\View\View;

class AgeingController extends Controller
{
    public function __construct(private readonly AgeingService $ageingService) {}

    public function receivables(): View
    {
        $report = $this->ageingService->report(InvoiceType::SELL);
        $overdue = $this->ageingService->overdueWorklist(InvoiceType::SELL);

        return view('ageing.report', [
            'title' => __('Accounts Receivable Ageing'),
            'type' => 'receivables',
            'report' => $report,
            'overdue' => $overdue,
        ]);
    }

    public function payables(): View
    {
        $report = $this->ageingService->report(InvoiceType::BUY);
        $overdue = $this->ageingService->overdueWorklist(InvoiceType::BUY);

        return view('ageing.report', [
            'title' => __('Accounts Payable Ageing'),
            'type' => 'payables',
            'report' => $report,
            'overdue' => $overdue,
        ]);
    }

    public function statement(Customer $customer, string $type): View
    {
        $invoiceType = $type === 'payables' ? InvoiceType::BUY : InvoiceType::SELL;
        $lines = $this->ageingService->statement($customer, $invoiceType);

        return view('ageing.statement', [
            'title' => $type === 'payables' ? __('Supplier Statement') : __('Customer Statement'),
            'type' => $type,
            'customer' => $customer,
            'lines' => $lines,
        ]);
    }
}
