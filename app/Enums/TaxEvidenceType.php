<?php

namespace App\Enums;

enum TaxEvidenceType: string
{
    case INVOICE_COPY = 'invoice_copy';

    case PAYMENT_PROOF = 'payment_proof';

    case TRANSPORT_WAYBILL = 'transport_waybill';

    case CONTRACT = 'contract';

    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_COPY => __('Invoice copy'),
            self::PAYMENT_PROOF => __('Payment proof'),
            self::TRANSPORT_WAYBILL => __('Transport waybill'),
            self::CONTRACT => __('Contract'),
            self::OTHER => __('Other'),
        };
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
