<?php

namespace App\Services\AiVision\Parsers;

use App\Services\AiVision\Contracts\InvoiceVisionParserInterface;

class DeterministicMockVisionParser implements InvoiceVisionParserInterface
{
    /**
     * Parse invoice file using deterministic extraction heuristics.
     */
    public function parseInvoiceFile(string $absolutePath, string $mimeType): array
    {
        $filename = basename($absolutePath);

        // Deterministic generation based on filename or standard realistic sample
        $hash = crc32($filename);
        $invoiceNum = 'INV-'.(1000 + ($hash % 9000));

        $item1Qty = 2.0;
        $item1Price = 1500000;
        $item1Total = (int) ($item1Qty * $item1Price);

        $item2Qty = 5.0;
        $item2Price = 400000;
        $item2Total = (int) ($item2Qty * $item2Price);

        $subtotal = $item1Total + $item2Total; // 3,000,000 + 2,000,000 = 5,000,000
        $vat = (int) ($subtotal * 0.10); // 10% Iranian VAT = 500,000
        $total = $subtotal + $vat; // 5,500,000

        return [
            'vendor' => [
                'name' => 'شرکت بازرگانی پارس آریا (تأمین‌کننده نمونه)',
                'national_code' => '10101234567',
                'phone' => '02188776655',
                'address' => 'تهران، خیابان ولیعصر، برج سپهر، طبقه ۴',
            ],
            'invoice_number' => $invoiceNum,
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'name' => 'کاغذ A4 کپی‌مکس بسته ۵۰۰ عددی',
                    'quantity' => $item1Qty,
                    'unit_price' => $item1Price,
                    'total_price' => $item1Total,
                    'vat_amount' => (int) ($item1Total * 0.10),
                    'discount' => 0,
                ],
                [
                    'name' => 'خودکار بیک آبی اصل بسته ۵۰ تایی',
                    'quantity' => $item2Qty,
                    'unit_price' => $item2Price,
                    'total_price' => $item2Total,
                    'vat_amount' => (int) ($item2Total * 0.10),
                    'discount' => 0,
                ],
            ],
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'total_amount' => $total,
            'confidence_score' => 0.94,
            'notes' => 'استخراج خودکار از سند کاغذی اسکن شده توسط موتور هوش مصنوعی دخل',
        ];
    }
}
