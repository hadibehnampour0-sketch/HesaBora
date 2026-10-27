<?php

namespace App\Support\Moadian;

/**
 * Clean-Room Moadian Invoice Aggregate DTO.
 *
 * Combines header, item rows, and payments for Moadian submission.
 *
 * License: MIT
 */
class MoadianInvoice
{
    /** @var InvoiceItem[] */
    public array $items = [];

    /** @var Payment[] */
    public array $payments = [];

    public function __construct(public InvoiceHeader $header) {}

    public function addItem(InvoiceItem $item): void
    {
        $this->items[] = $item;
    }

    public function addPayment(Payment $payment): void
    {
        $this->payments[] = $payment;
    }

    public function toArray(): array
    {
        $result = [
            'header' => $this->header->toArray(),
            'body' => array_map(fn (InvoiceItem $item) => $item->toArray(), $this->items),
        ];

        if (! empty($this->payments)) {
            $result['payments'] = array_map(fn (Payment $payment) => $payment->toArray(), $this->payments);
        }

        return $result;
    }
}
