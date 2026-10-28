<?php

namespace App\Support\Moadian;

use JsonSerializable;

/**
 * Electronic Invoice Settlement Payment Record conforming to INTA Schema v2.x.
 *
 * Encapsulates payment transaction parameters (instrument code, payment value,
 * switch/terminal identifiers, tracking numbers, and settlement timestamps).
 */
class Payment implements JsonSerializable
{
    /**
     * Internal container for statutory settlement attributes.
     *
     * @var array<string, mixed>
     */
    protected array $payload = [];

    /**
     * Instantiate payment record with optional pre-filled attributes.
     *
     * @param array<string, mixed> $initialAttributes
     */
    public function __construct(array $initialAttributes = [])
    {
        foreach ($initialAttributes as $field => $val) {
            if ($val !== null) {
                $this->payload[$field] = $val;
            }
        }
    }

    /**
     * Fluent setter for settlement method code (1 = Cash, 2 = POS, 3 = Transfer, etc.).
     */
    public function setSettlementMethod(int $methodCode): self
    {
        $this->payload['pmt'] = $methodCode;

        return $this;
    }

    /**
     * Fluent setter for paid transaction amount.
     */
    public function setPaidAmount(float|int $amount): self
    {
        $this->payload['pv'] = $amount;

        return $this;
    }

    /**
     * Fluent setter for POS terminal / switch identifiers.
     */
    public function setTerminalDetails(?string $terminalId, ?string $switchId = null, ?string $accountNumber = null): self
    {
        if ($terminalId !== null) {
            $this->payload['trmn'] = $terminalId;
        }
        if ($switchId !== null) {
            $this->payload['iinn'] = $switchId;
        }
        if ($accountNumber !== null) {
            $this->payload['acn'] = $accountNumber;
        }

        return $this;
    }

    /**
     * Fluent setter for reference / tracking number.
     */
    public function setTrackingNumber(string $trackingNumber): self
    {
        $this->payload['trn'] = $trackingNumber;

        return $this;
    }

    /**
     * Dynamic property setter for statutory INTA acronyms.
     */
    public function __set(string $property, mixed $value): void
    {
        if ($value !== null) {
            $this->payload[$property] = $value;
        } else {
            unset($this->payload[$property]);
        }
    }

    /**
     * Dynamic property getter for statutory INTA acronyms.
     */
    public function __get(string $property): mixed
    {
        return $this->payload[$property] ?? null;
    }

    /**
     * Dynamic property checker.
     */
    public function __isset(string $property): bool
    {
        return isset($this->payload[$property]);
    }

    /**
     * Unset dynamic property.
     */
    public function __unset(string $property): void
    {
        unset($this->payload[$property]);
    }

    /**
     * Export raw attributes for invoice envelope serialization.
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * Serializes to JSON envelope representation.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
