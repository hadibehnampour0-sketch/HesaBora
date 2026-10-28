<?php

namespace App\Support\Moadian;

use JsonSerializable;

/**
 * Composite Electronic Invoice Aggregate for Iranian Tax Authority transmission.
 *
 * Assembles statutory header metadata, goods/services line items, and payment
 * settlement records into the standardized taxpayer e-invoice payload structure.
 */
class MoadianInvoice implements JsonSerializable
{
    /**
     * Internal sequence of goods and services line items.
     *
     * @var array<int, InvoiceItem>
     */
    protected array $goodsAndServices = [];

    /**
     * Internal sequence of settlement payment vouchers.
     *
     * @var array<int, Payment>
     */
    protected array $paymentRecords = [];

    /**
     * Initialize electronic invoice aggregate with its statutory header.
     */
    public function __construct(protected InvoiceHeader $documentHeader)
    {
    }

    /**
     * Retrieve the document header component.
     */
    public function getHeader(): InvoiceHeader
    {
        return $this->documentHeader;
    }

    /**
     * Retrieve all line items attached to this invoice.
     *
     * @return InvoiceItem[]
     */
    public function getItems(): array
    {
        return $this->goodsAndServices;
    }

    /**
     * Retrieve all payment vouchers attached to this invoice.
     *
     * @return Payment[]
     */
    public function getPayments(): array
    {
        return $this->paymentRecords;
    }

    /**
     * Append a line item (good/service) to the invoice body.
     */
    public function addItem(InvoiceItem $item): self
    {
        $this->goodsAndServices[] = $item;

        return $this;
    }

    /**
     * Append a settlement voucher to the payment record section.
     */
    public function addPayment(Payment $payment): self
    {
        $this->paymentRecords[] = $payment;

        return $this;
    }

    /**
     * Convert aggregate into the structured packet dictionary expected by INTA gateway.
     */
    public function toArray(): array
    {
        $packet = [];
        $packet['header'] = $this->documentHeader->toArray();

        $rows = [];
        foreach ($this->goodsAndServices as $row) {
            $rows[] = $row->toArray();
        }
        $packet['body'] = $rows;

        if (count($this->paymentRecords) > 0) {
            $settlements = [];
            foreach ($this->paymentRecords as $settlement) {
                $settlements[] = $settlement->toArray();
            }
            $packet['payments'] = $settlements;
        }

        return $packet;
    }

    /**
     * JsonSerializable implementation for JSON encoding.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
