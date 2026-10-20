<?php

namespace App\Services\Inquiry\Validators;

class IranianCardValidator
{
    protected static array $binToBank = [
        '603799' => ['name' => 'بانک ملی ایران', 'sheba_code' => '017'],
        '589210' => ['name' => 'بانک سپه', 'sheba_code' => '015'],
        '627648' => ['name' => 'بانک توسعه صادرات', 'sheba_code' => '020'],
        '627961' => ['name' => 'بانک صنعت و معدن', 'sheba_code' => '011'],
        '603770' => ['name' => 'بانک کشاورزی', 'sheba_code' => '016'],
        '628023' => ['name' => 'بانک مسکن', 'sheba_code' => '014'],
        '627760' => ['name' => 'پست بانک ایران', 'sheba_code' => '021'],
        '502908' => ['name' => 'بانک توسعه تعاون', 'sheba_code' => '022'],
        '627412' => ['name' => 'بانک اقتصاد نوین', 'sheba_code' => '055'],
        '622106' => ['name' => 'بانک پارسیان', 'sheba_code' => '054'],
        '639194' => ['name' => 'بانک پارسیان', 'sheba_code' => '054'],
        '502229' => ['name' => 'بانک پاسارگاد', 'sheba_code' => '057'],
        '627488' => ['name' => 'بانک کارآفرین', 'sheba_code' => '053'],
        '621986' => ['name' => 'بانک سامان', 'sheba_code' => '056'],
        '639346' => ['name' => 'بانک سینا', 'sheba_code' => '059'],
        '639607' => ['name' => 'بانک سرمایه', 'sheba_code' => '058'],
        '636214' => ['name' => 'بانک آینده', 'sheba_code' => '062'],
        '502806' => ['name' => 'بانک شهر', 'sheba_code' => '061'],
        '504706' => ['name' => 'بانک شهر', 'sheba_code' => '061'],
        '502938' => ['name' => 'بانک دی', 'sheba_code' => '066'],
        '603769' => ['name' => 'بانک صادرات ایران', 'sheba_code' => '019'],
        '610433' => ['name' => 'بانک ملت', 'sheba_code' => '012'],
        '627353' => ['name' => 'بانک تجارت', 'sheba_code' => '018'],
        '589463' => ['name' => 'بانک رفاه کارگران', 'sheba_code' => '013'],
        '627381' => ['name' => 'بانک حکمت ایرانیان', 'sheba_code' => '065'],
        '505785' => ['name' => 'بانک ایران زمین', 'sheba_code' => '069'],
        '636795' => ['name' => 'بانک مرکزی', 'sheba_code' => '010'],
        '606373' => ['name' => 'بانک قرض‌الحسنه مهر ایران', 'sheba_code' => '060'],
        '504172' => ['name' => 'بانک قرض‌الحسنه رسالت', 'sheba_code' => '070'],
        '505416' => ['name' => 'بانک گردشگری', 'sheba_code' => '064'],
        '505801' => ['name' => 'موسسه اعتباری کوثر', 'sheba_code' => '073'],
        '606256' => ['name' => 'موسسه اعتباری ملل', 'sheba_code' => '075'],
    ];

    /**
     * Cleans card number string (removes dashes, spaces).
     */
    public static function clean(?string $cardNumber): string
    {
        return preg_replace('/\D/', '', $cardNumber ?? '');
    }

    /**
     * Validates card number using 16 digits and Luhn algorithm.
     */
    public static function validate(?string $cardNumber): bool
    {
        $clean = self::clean($cardNumber);

        if (strlen($clean) !== 16) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 16; $i++) {
            $val = (int) $clean[$i];
            if ($i % 2 === 0) {
                $val *= 2;
                if ($val > 9) {
                    $val -= 9;
                }
            }
            $sum += $val;
        }

        return ($sum % 10) === 0;
    }

    /**
     * Get Bank details from Card BIN (first 6 digits).
     */
    public static function getBankDetails(?string $cardNumber): array
    {
        $clean = self::clean($cardNumber);
        $bin = substr($clean, 0, 6);

        if (isset(self::$binToBank[$bin])) {
            return self::$binToBank[$bin];
        }

        return ['name' => 'بانک عضو شتاب', 'sheba_code' => '000'];
    }
}
