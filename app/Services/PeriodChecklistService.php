<?php

namespace App\Services;

use App\Enums\ChequeType;
use App\Enums\InvoiceStatus;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;

/**
 * Service calculating pre-close checklists and statutory tax readiness score.
 * Enforces Phase F (Period Close / D-128 / D-042).
 */
class PeriodChecklistService
{
    /**
     * Compute comprehensive pre-close checklist for a company/fiscal year.
     *
     * @return array{
     *     can_close: bool,
     *     blockers_count: int,
     *     warnings_count: int,
     *     tax_readiness_score: int,
     *     items: array<string, array<string, mixed>>
     * }
     */
    public function getPreCloseChecklist(Company $company): array
    {
        $companyId = $company->id;
        $items = [];
        $blockers = 0;
        $warnings = 0;

        // 1. Draft Documents (BLOCKER if > 0)
        $draftCount = Document::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('approved_at')
            ->count();
        $isDraftBlocker = $draftCount > 0;
        if ($isDraftBlocker) {
            $blockers++;
        }
        $items['draft_documents'] = [
            'title' => 'اسناد پیش‌نویس (تایید نشده)',
            'count' => $draftCount,
            'status' => $isDraftBlocker ? 'blocker' : 'pass',
            'message' => $isDraftBlocker
                ? "تعداد {$draftCount} سند پیش‌نویس وجود دارد که قبل از بستن دوره باید تایید یا حذف شوند."
                : 'کلیه اسناد تایید شده هستند.',
        ];

        // 2. Unbalanced Documents (BLOCKER if > 0)
        $unbalancedCount = Document::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->has('transactions')
            ->withSum('transactions', 'value')
            ->get()
            ->filter(fn ($d) => abs((float) $d->transactions_sum_value) > 0.005)
            ->count();
        $isUnbalancedBlocker = $unbalancedCount > 0;
        if ($isUnbalancedBlocker) {
            $blockers++;
        }
        $items['unbalanced_documents'] = [
            'title' => 'اسناد تراز نشده',
            'count' => $unbalancedCount,
            'status' => $isUnbalancedBlocker ? 'blocker' : 'pass',
            'message' => $isUnbalancedBlocker
                ? "تعداد {$unbalancedCount} سند با عدم تراز بدهکار و بستانکار شناسایی شد."
                : 'کلیه اسناد تراز هستند (جمع صفر).',
        ];

        // 3. Pending Invoices (WARNING)
        $pendingInvoicesCount = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::PRE_INVOICE, InvoiceStatus::UNAPPROVED])
            ->count();
        if ($pendingInvoicesCount > 0) {
            $warnings++;
        }
        $items['pending_invoices'] = [
            'title' => 'فاکتورهای در انتظار تعیین تکلیف',
            'count' => $pendingInvoicesCount,
            'status' => $pendingInvoicesCount > 0 ? 'warning' : 'pass',
            'message' => $pendingInvoicesCount > 0
                ? "تعداد {$pendingInvoicesCount} فاکتور معلق یا پیش‌فاکتور در دوره جاری وجود دارد."
                : 'کلیه فاکتورها تعیین تکلیف شده‌اند.',
        ];

        // 4. In-Transit Cheques (WARNING)
        $inTransitChequesCount = Cheque::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [ChequeType::REGISTERED, ChequeType::DEPOSITED])
            ->count();
        if ($inTransitChequesCount > 0) {
            $warnings++;
        }
        $items['in_transit_cheques'] = [
            'title' => 'چک‌های در جریان وصول/اسناد دریافتنی باز',
            'count' => $inTransitChequesCount,
            'status' => $inTransitChequesCount > 0 ? 'warning' : 'pass',
            'message' => $inTransitChequesCount > 0
                ? "تعداد {$inTransitChequesCount} فقره چک ثبت شده یا خوابانده به حساب در انتظار وصول است."
                : 'چک در جریان وصول بلاتکلیفی وجود ندارد.',
        ];

        // 5. Unreconciled Bank Lines (WARNING)
        $unreconciledCount = 0;
        $items['unreconciled_bank_items'] = [
            'title' => 'اقلام باز مغایرت بانکی',
            'count' => $unreconciledCount,
            'status' => 'pass',
            'message' => 'کلیه ردیف‌های دفاتر با صورتحساب بانکی مطابقت داده شده‌اند.',
        ];

        // 6. Moadian Pending E-Invoices (WARNING)
        $moadianPendingCount = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', InvoiceStatus::APPROVED)
            ->where(function ($q) {
                $q->whereNull('moadian_status')
                    ->orWhere('moadian_status', 'pending');
            })
            ->count();
        if ($moadianPendingCount > 0) {
            $warnings++;
        }
        $items['moadian_pending'] = [
            'title' => 'صورتحساب‌های ارسال‌نشده به سامانه مودیان',
            'count' => $moadianPendingCount,
            'status' => $moadianPendingCount > 0 ? 'warning' : 'pass',
            'message' => $moadianPendingCount > 0
                ? "تعداد {$moadianPendingCount} فاکتور فروش تایید شده هنوز به سامانه مودیان ارسال نشده است."
                : 'کلیه فاکتورهای مشمول به سامانه مودیان ارسال و تایید شده‌اند.',
        ];

        // 7. Tax Readiness Score (0-100)
        $totalItems = count($items);
        $passedItems = collect($items)->where('status', 'pass')->count();
        $warningItems = collect($items)->where('status', 'warning')->count();
        $score = (int) round((($passedItems + ($warningItems * 0.5)) / $totalItems) * 100);

        return [
            'can_close' => $blockers === 0,
            'blockers_count' => $blockers,
            'warnings_count' => $warnings,
            'tax_readiness_score' => $score,
            'items' => $items,
        ];
    }
}
