<?php

namespace App\Services\Inquiry\Validators;

class IranianShebaValidator
{
    protected static array $bankNames = [
        '010' => 'بانک مرکزی',
        '011' => 'بانک صنعت و معدن',
        '012' => 'بانک ملت',
        '013' => 'بانک رفاه کارگران',
        '014' => 'بانک مسکن',
        '015' => 'بانک سپه',
        '016' => 'بانک کشاورزی',
        '017' => 'بانک ملی ایران',
        '018' => 'بانک تجارت',
        '019' => 'بانک صادرات ایران',
        '020' => 'بانک توسعه صادرات',
        '021' => 'پست بانک ایران',
        '022' => 'بانک توسعه تعاون',
        '051' => 'موسسه اعتباری توسعه',
        '053' => 'بانک کارآفرین',
        '054' => 'بانک پارسیان',
        '055' => 'بانک اقتصاد نوین',
        '056' => 'بانک سامان',
        '057' => 'بانک پاسارگاد',
        '058' => 'بانک سرمایه',
        '059' => 'بانک سینا',
        '060' => 'بانک قرض‌الحسنه مهر ایران',
        '061' => 'بانک شهر',
        '062' => 'بانک آینده',
        '063' => 'بانک انصار',
        '064' => 'بانک گردشگری',
        '065' => 'بانک حکمت ایرانیان',
        '066' => 'بانک دی',
        '069' => 'بانک ایران زمین',
        '070' => 'بانک قرض‌الحسنه رسالت',
        '073' => 'موسسه اعتباری کوثر',
        '075' => 'موسسه اعتباری ملل',
        '078' => 'بانک خاورمیانه',
        '080' => 'موسسه اعتباری نور',
    ];

    /**
     * Clean and format Sheba string (removes spaces, dashes, converts to uppercase).
     */
    public static function clean(?string $sheba): string
    {
        if (empty($sheba)) {
            return '';
        }

        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $sheba));
        if (! str_starts_with($clean, 'IR')) {
            $clean = 'IR' . $clean;
        }

        return $clean;
    }

    /**
     * Validates an Iranian Sheba / IBAN using ISO 7064 Mod 97-10.
     */
    public static function validate(?string $sheba): bool
    {
        $clean = self::clean($sheba);

        if (strlen($clean) !== 26) {
            return false;
        }

        if (! preg_match('/^IR\d{24}$/', $clean)) {
            return false;
        }

        // Reorder for ISO 7064: take first 4 chars ('IR' + 2 check digits) and move to end
        // Replace 'I' with 18 and 'R' with 27 ('IR' => '1827')
        $reordered = substr($clean, 4) . '1827' . substr($clean, 2, 2);

        // Compute modulo 97 on large numeric string
        return self::mod97($reordered) === 1;
    }

    /**
     * Extract bank code from Sheba (digits 3, 4, 5 of the 24-digit Sheba body).
     */
    public static function getBankCode(?string $sheba): ?string
    {
        if (empty($sheba)) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $sheba));
        if (str_starts_with($clean, 'IR')) {
            $clean = substr($clean, 2);
        }

        if (strlen($clean) < 5) {
            return null;
        }

        return substr($clean, 2, 3);
    }

    /**
     * Get Persian Bank Name from Sheba.
     */
    public static function getBankName(?string $sheba): string
    {
        $code = self::getBankCode($sheba);
        return self::$bankNames[$code] ?? 'بانک نامشخص';
    }

    /**
     * Big number modulo 97 implementation.
     */
    protected static function mod97(string $numericString): int
    {
        $remainder = 0;
        $length = strlen($numericString);

        for ($i = 0; $i < $length; $i += 7) {
            $chunk = $remainder . substr($numericString, $i, 7);
            $remainder = ((int) $chunk) % 97;
        }

        return $remainder;
    }
}
