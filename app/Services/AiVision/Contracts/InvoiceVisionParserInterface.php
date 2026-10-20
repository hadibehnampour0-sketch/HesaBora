<?php

namespace App\Services\AiVision\Contracts;

interface InvoiceVisionParserInterface
{
    /**
     * Parse an invoice image or document file and extract structured fields.
     *
     * Expected return structure:
     * [
     *     'vendor' => [
     *         'name' => string|null,
     *         'national_code' => string|null,
     *         'phone' => string|null,
     *         'address' => string|null,
     *     ],
     *     'invoice_number' => string|null,
     *     'invoice_date' => string|null,
     *     'items' => [
     *         [
     *             'name' => string,
     *             'quantity' => float,
     *             'unit_price' => int,
     *             'total_price' => int,
     *             'vat_amount' => int,
     *             'discount' => int,
     *         ],
     *     ],
     *     'subtotal' => int,
     *     'vat_amount' => int,
     *     'total_amount' => int,
     *     'confidence_score' => float,
     *     'notes' => string|null,
     * ]
     */
    public function parseInvoiceFile(string $absolutePath, string $mimeType): array;
}
