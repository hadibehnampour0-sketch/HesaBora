<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * P12.3 (Seasonal Tax & VAT Reporting Engine, Article 169/ماده ۱۶۹). An
 * invoice-level approximation of Iran's quarterly VAT return -- NOT a
 * verified, byte-level TTMS filing format (no official schema was
 * available to validate against; see the docblock on `report()` and the
 * Blade view's own disclaimer). Deliberately built entirely from
 * `invoices.vat`/`invoices.amount` (already-aggregated per-invoice
 * totals set by InvoiceTransactionBuilder), not a per-item/per-sstid
 * breakdown -- the latter would require joining invoice_items ->
 * product/service -> sstid -> TaxRule, a materially larger undertaking
 * out of scope for this pass.
 *
 * Jalali quarters (Iran's actual fiscal seasons, NOT the Gregorian
 * calendar): Q1 بهار = Farvardin-Khordad (months 1-3), Q2 تابستان =
 * Tir-Shahrivar (4-6), Q3 پاییز = Mehr-Azar (7-9), Q4 زمستان =
 * Dey-Esfand (10-12). A quarter's end date is computed as "the day
 * before the NEXT quarter's first day" (via `jalali_to_gregorian()`)
 * rather than hardcoding Esfand's 29/30-day leap-year length.
 */
class SeasonalTaxReportService
{
    private const QUARTER_START_MONTHS = [1 => 1, 2 => 4, 3 => 7, 4 => 10];

    private const SELL_TYPES = [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::VOID];

    private const BUY_TYPES = [InvoiceType::BUY, InvoiceType::RETURN_BUY];

    /**
     * @return array{0: string, 1: string} [startDate, endDate] as Gregorian 'Y-m-d' strings.
     */
    public function quarterDateRange(int $jalaliYear, int $quarter): array
    {
        if (! isset(self::QUARTER_START_MONTHS[$quarter])) {
            throw ValidationException::withMessages(['quarter' => [__('The quarter must be between 1 and 4.')]]);
        }

        $startMonth = self::QUARTER_START_MONTHS[$quarter];
        [$sgy, $sgm, $sgd] = jalali_to_gregorian($jalaliYear, $startMonth, 1);
        $start = Carbon::create($sgy, $sgm, $sgd);

        $nextQuarterYear = $quarter === 4 ? $jalaliYear + 1 : $jalaliYear;
        $nextQuarterMonth = $quarter === 4 ? 1 : self::QUARTER_START_MONTHS[$quarter + 1];
        [$egy, $egm, $egd] = jalali_to_gregorian($nextQuarterYear, $nextQuarterMonth, 1);
        $end = Carbon::create($egy, $egm, $egd)->subDay();

        return [$start->toDateString(), $end->toDateString()];
    }

    public function quarterLabel(int $quarter): string
    {
        return match ($quarter) {
            1 => __('Spring'),
            2 => __('Summer'),
            3 => __('Autumn'),
            4 => __('Winter'),
            default => throw ValidationException::withMessages(['quarter' => [__('The quarter must be between 1 and 4.')]]),
        };
    }

    /**
     * @return array{jalali_year: int, quarter: int, quarter_label: string, start_date: string, end_date: string, taxable_sales: float, exempt_sales: float, vat_collected: float, taxable_purchases: float, creditable_input_vat: float, net_vat_payable: float}
     */
    public function report(int $companyId, int $jalaliYear, int $quarter): array
    {
        [$startDate, $endDate] = $this->quarterDateRange($jalaliYear, $quarter);

        $rows = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('invoice_type,
                SUM(CASE WHEN vat > 0 THEN amount - vat ELSE 0 END) as taxable_base,
                SUM(CASE WHEN vat = 0 THEN amount ELSE 0 END) as exempt_base,
                SUM(vat) as vat_sum')
            ->groupBy('invoice_type')
            ->get();

        $taxableSales = 0.0;
        $exemptSales = 0.0;
        $vatCollected = 0.0;
        $taxablePurchases = 0.0;
        $creditableInputVat = 0.0;

        foreach ($rows as $row) {
            // Invoice::$casts already turns this into an InvoiceType enum
            // instance on access (Eloquent applies model casts to a raw
            // selectRaw() column too, not just plain attributes) -- no
            // InvoiceType::from() conversion needed or possible here.
            $type = $row->invoice_type;
            // A RETURN_SELL/RETURN_BUY/VOID row's own amount/vat columns
            // are stored as POSITIVE magnitudes (copied verbatim from the
            // original invoice's items) -- only the POSTED TRANSACTIONS
            // are negated at write time (InvoiceService::approveInvoice()'s
            // isVoid() branch, InvoiceTransactionBuilder's sign-flip match
            // for RETURN_*). This report reconstructs that same net effect
            // by subtracting these three types instead of adding them.
            $sign = in_array($type, [InvoiceType::RETURN_SELL, InvoiceType::RETURN_BUY, InvoiceType::VOID], true) ? -1 : 1;

            if (in_array($type, self::SELL_TYPES, true)) {
                $taxableSales += $sign * (float) $row->taxable_base;
                $exemptSales += $sign * (float) $row->exempt_base;
                $vatCollected += $sign * (float) $row->vat_sum;
            } elseif (in_array($type, self::BUY_TYPES, true)) {
                $taxablePurchases += $sign * (float) $row->taxable_base;
                $creditableInputVat += $sign * (float) $row->vat_sum;
            }
        }

        return [
            'jalali_year' => $jalaliYear,
            'quarter' => $quarter,
            'quarter_label' => $this->quarterLabel($quarter),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'taxable_sales' => round($taxableSales, 2),
            'exempt_sales' => round($exemptSales, 2),
            'vat_collected' => round($vatCollected, 2),
            'taxable_purchases' => round($taxablePurchases, 2),
            'creditable_input_vat' => round($creditableInputVat, 2),
            'net_vat_payable' => round($vatCollected - $creditableInputVat, 2),
        ];
    }
}
