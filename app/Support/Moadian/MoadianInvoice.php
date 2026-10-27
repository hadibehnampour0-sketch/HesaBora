<?php

namespace App\Support\Moadian;

use JsonSerializable;

/**
 * Composite Electronic Invoice Aggregate containing header, line items, and payment allocations.
 */
class MoadianInvoice implements JsonSerializable
{
    /** @var InvoiceItem[] */
    public array $items = [];

    /** @var Payment[] */
    public array $payments = [];

    public function __construct(public InvoiceHeader $header) {}

    public function addItem(InvoiceItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function addPayment(Payment $payment): self
    {
        $this->payments[] = $payment;

        return $this;
    }

    public function toArray(): array
    {
        $payload = [
            'header' => $this->header->toArray(),
            'body' => array_map(fn (InvoiceItem $item) => $item->toArray(), $this->items),
        ];

        if (! empty($this->payments)) {
            $payload['payments'] = array_map(fn (Payment $p) => $p->toArray(), $this->payments);
        }

        return $payload;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
