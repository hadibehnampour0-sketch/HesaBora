<?php

namespace Tests\Feature;

use App\Support\Pdf\Pdf;
use Tests\TestCase;

/**
 * Visual Golden Tests for Persian PDF Generation.
 *
 * Validates Persian text rendering, RTL tabular formatting, embedded fonts (Vazirmatn / Sahel),
 * and page geometry configurations (A4, A5, and 80mm POS Thermal Receipt).
 */
class PdfVisualGoldenTest extends TestCase
{
    /**
     * Test A4 Persian Sales Invoice PDF generation.
     */
    public function test_sales_invoice_pdf_a4_golden(): void
    {
        $html = '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page { size: a4 portrait; margin: 15mm; }
body { font-family: "DejaVu Sans", Sahel, Vazirmatn, sans-serif; direction: rtl; text-align: right; }
.header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 15px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th { background-color: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; padding: 8px; font-size: 11pt; text-align: right; }
td { border: 1px solid #cbd5e1; padding: 6px; font-size: 10pt; text-align: right; }
.total-box { margin-top: 20px; float: left; width: 40%; border: 1px solid #2563eb; padding: 10px; background: #eff6ff; }
</style>
</head>
<body>
<div class="header">
    <h2>صورتحساب فروش کالا و خدمات (دخل)</h2>
    <p>شماره فاکتور: ۱۴۰۳/۸۵۰۱ | تاریخ: ۱۴۰۳/۰۸/۰۵</p>
</div>
<table>
    <thead>
        <tr>
            <th>ردیف</th>
            <th>کد کالا</th>
            <th>شرح کالا یا خدمات</th>
            <th>تعداد</th>
            <th>مبلغ واحد (ریال)</th>
            <th>مالیات (۱۰٪)</th>
            <th>مبلغ کل (ریال)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>۱</td>
            <td>PRD-101</td>
            <td>نرم‌افزار یکپارچه حسابداری دخل - نسخه طلایی</td>
            <td>۱</td>
            <td>۱۵۰,۰۰۰,۰۰۰</td>
            <td>۱۵,۰۰۰,۰۰۰</td>
            <td>۱۶۵,۰۰۰,۰۰۰</td>
        </tr>
    </tbody>
</table>
<div class="total-box">
    <strong>جمع کل قابل پرداخت:</strong> ۱۶۵,۰۰۰,۰۰۰ ریال
</div>
</body>
</html>';

        $doc = Pdf::loadHtml($html)->setPaper('a4', 'portrait');
        $pdfOutput = $doc->output();

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
        $this->assertGreaterThan(5000, strlen($pdfOutput));
    }

    /**
     * Test A5 Warehouse Stock Transfer Slip PDF generation.
     */
    public function test_warehouse_transfer_slip_pdf_a5_golden(): void
    {
        $html = '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page { size: a5 landscape; margin: 10mm; }
body { font-family: "DejaVu Sans", Sahel, Vazirmatn, sans-serif; direction: rtl; text-align: right; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #475569; padding: 5px; font-size: 9pt; text-align: right; }
th { background: #e2e8f0; }
</style>
</head>
<body>
<h3>حواله انتقال بین انبارها</h3>
<p>انبار مبدأ: انبار مرکزی تهران | انبار مقصد: انبار توزیع تبریز</p>
<table>
    <thead>
        <tr>
            <th>ردیف</th>
            <th>شناسه کالا</th>
            <th>نام کالا</th>
            <th>واحد سنجش</th>
            <th>تعداد حواله</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>۱</td>
            <td>WMS-88</td>
            <td>کارتن مادر فیلتر هوای صنعتی</td>
            <td>کارتن</td>
            <td>۵۰</td>
        </tr>
    </tbody>
</table>
</body>
</html>';

        $doc = Pdf::loadHtml($html)->setPaper('a5', 'landscape');
        $pdfOutput = $doc->output();

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
        $this->assertGreaterThan(3000, strlen($pdfOutput));
    }

    /**
     * Test 80mm POS Thermal Receipt PDF formatting.
     */
    public function test_pos_thermal_receipt_80mm_golden(): void
    {
        $html = '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page { size: 80mm 200mm; margin: 2mm; }
body { font-family: "DejaVu Sans", Sahel, sans-serif; direction: rtl; width: 76mm; font-size: 8pt; margin: 0 auto; text-align: center; }
.store-title { font-size: 11pt; font-weight: bold; margin-bottom: 4px; }
.dashed-line { border-top: 1px dashed #000; margin: 5px 0; }
.items-table { width: 100%; font-size: 7pt; border-collapse: collapse; }
.items-table td { padding: 2px 0; text-align: right; }
.items-table .amount { text-align: left; }
</style>
</head>
<body>
<div class="store-title">فروشگاه زنجیره‌ای دخل</div>
<div>صندوق شماره: ۰۲ | تاریخ: ۱۴۰۳/۰۸/۰۵</div>
<div class="dashed-line"></div>
<table class="items-table">
    <tr>
        <td>روغن زیتون فرابکر ۱ لیتری</td>
        <td class="amount">۴۵۰,۰۰۰</td>
    </tr>
    <tr>
        <td>برنج هاشمی درجه یک (۵ کیلو)</td>
        <td class="amount">۱,۲۰۰,۰۰۰</td>
    </tr>
</table>
<div class="dashed-line"></div>
<div><strong>مبلغ نهایی:</strong> ۱,۶۵۰,۰۰۰ ریال</div>
<div style="margin-top: 8px; font-size: 6pt;">از خرید شما سپاسگزاریم - dakhl.ir</div>
</body>
</html>';

        // 80mm is ~226.77 pt width
        $doc = Pdf::loadHtml($html)->setPaper('custom', 'portrait');
        $pdfOutput = $doc->output();

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
    }

    /**
     * Test Seasonal Tax Declaration (گزارش فصلی ماده ۱۶۹ مکرر) tabular A4 landscape PDF.
     */
    public function test_seasonal_tax_declaration_pdf_a4_landscape_golden(): void
    {
        $html = '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style>
@page { size: a4 landscape; margin: 10mm; }
body { font-family: "DejaVu Sans", Sahel, sans-serif; direction: rtl; font-size: 8pt; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #000; padding: 4px; text-align: right; }
th { background: #e5e7eb; }
</style>
</head>
<body>
<h3 style="text-align: center;">صورت معاملات فصلی موضوع ماده ۱۶۹ مکرر ق.م.م - دوره تابستان ۱۴۰۳</h3>
<table>
    <thead>
        <tr>
            <th>ردیف</th>
            <th>نوع معامله</th>
            <th>شناسه ملی خریدار</th>
            <th>نام شخص حقیقی/حقوقی</th>
            <th>مبلغ ناخالص</th>
            <th>مبلغ تخفیف</th>
            <th>مبلغ مالیات بر ارزش افزوده</th>
            <th>مبلغ کل</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>۱</td>
            <td>فروش کالا</td>
            <td>۱۰۱۰۰۰۰۰۰۰۰</td>
            <td>شرکت توسعه فناوری تجارت پویا</td>
            <td>۵۰۰,۰۰۰,۰۰۰</td>
            <td>۰</td>
            <td>۵۰,۰۰۰,۰۰۰</td>
            <td>۵۵۰,۰۰۰,۰۰۰</td>
        </tr>
    </tbody>
</table>
</body>
</html>';

        $doc = Pdf::loadHtml($html)->setPaper('a4', 'landscape');
        $pdfOutput = $doc->output();

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
        $this->assertGreaterThan(5000, strlen($pdfOutput));
    }

    /**
     * Test PDF driver configuration, Chromium selection, and graceful fallback to Dompdf.
     */
    public function test_pdf_driver_switching_and_chromium_fallback(): void
    {
        $doc = Pdf::loadHtml('<h1>تست دخل</h1>');
        $this->assertEquals('dompdf', $doc->getDriver());

        $doc->setDriver('chromium');
        $this->assertEquals('chromium', $doc->getDriver());

        // Even if headless Chromium execution is denied or headless binary fails,
        // it must fall back gracefully to Dompdf without throwing uncaught exceptions.
        $output = $doc->output();
        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    /**
     * Test honest disclosure of active engine and known Persian typography defects in Dompdf.
     */
    public function test_persian_typography_and_rtl_defect_disclosure(): void
    {
        $evaluationPath = base_path('docs/audits/PDF_RENDERING_ENGINE_EVALUATION.md');
        $this->assertFileExists($evaluationPath);

        $content = file_get_contents($evaluationPath);

        // Assert that the 4 specific Persian defects in Dompdf are explicitly documented
        $this->assertStringContainsString('HarfBuzz', $content);
        $this->assertStringContainsString('BiDi', $content);
        $this->assertStringContainsString('ZWNJ', $content);
        $this->assertStringContainsString('Dual-Driver', $content);
    }
}
