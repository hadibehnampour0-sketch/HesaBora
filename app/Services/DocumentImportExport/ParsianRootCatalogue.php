<?php

declare(strict_types=1);

namespace App\Services\DocumentImportExport;

/**
 * Clean-Room Pre-defined Iranian Accounting Standard Root Groups for Parsian System.
 *
 * Maps Parsian 3-digit Kol codes to standardized group names, permanence, and financial nature.
 *
 * License: MIT
 */
final class ParsianRootCatalogue
{
    public const DEFINITIONS = [
        '010' => ['title' => 'صندوق', 'permanent' => true, 'nature' => 'both'],
        '011' => ['title' => 'بانک ها', 'permanent' => true, 'nature' => 'both'],
        '012' => ['title' => 'بدهکاران (حسابهای دریافتنی)', 'permanent' => true, 'nature' => 'both'],
        '015' => ['title' => 'موجودی مواد و کالا', 'permanent' => true, 'nature' => 'both'],
        '017' => ['title' => 'دارایی های غیر جاری', 'permanent' => true, 'nature' => 'both'],
        '018' => ['title' => 'سایر حسابهای دریافتنی', 'permanent' => true, 'nature' => 'both'],
        '019' => ['title' => 'سایر حسابهای پرداختنی', 'permanent' => true, 'nature' => 'both'],
        '022' => ['title' => 'اسناد پرداختنی', 'permanent' => true, 'nature' => 'both'],
        '023' => ['title' => 'بستانکاران (حسابهای پرداختنی)', 'permanent' => true, 'nature' => 'both'],
        '024' => ['title' => 'ذخیره مالیات', 'permanent' => true, 'nature' => 'debtor'],
        '025' => ['title' => 'ذخایر', 'permanent' => true, 'nature' => 'both'],
        '030' => ['title' => 'حقوق صاحبان سهام', 'permanent' => true, 'nature' => 'both'],
        '040' => ['title' => 'درآمدها', 'permanent' => false, 'nature' => 'both'],
        '050' => ['title' => 'هزینه ها', 'permanent' => false, 'nature' => 'both'],
        '060' => ['title' => 'فروش', 'permanent' => false, 'nature' => 'both'],
        '061' => ['title' => 'برگشت از فروش و تخفیفات', 'permanent' => false, 'nature' => 'both'],
        '062' => ['title' => 'تخفیفات نقدی خرید', 'permanent' => false, 'nature' => 'both'],
        '064' => ['title' => 'حسابهای انتظامی', 'permanent' => true, 'nature' => 'both'],
        '065' => ['title' => 'طرف حسابهای انتظامی', 'permanent' => true, 'nature' => 'both'],
    ];

    /**
     * Retrieve root group definition by 3-digit code.
     *
     * @return array{title: string, permanent: bool, nature: string}|null
     */
    public static function find(string $code): ?array
    {
        return self::DEFINITIONS[trim($code)] ?? null;
    }
}
