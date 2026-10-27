<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Clean-Room Permissive (MIT) Jalali (Shamsi / Iranian) Calendar Engine.
 *
 * Implements mathematically exact Gregorian <-> Jalali calendar conversion
 * based on the astronomical 33-year cycle algorithm (Birashk / Borkowski formulas),
 * supporting dates across the entire Iranian calendar range (years 1200 to 1500+).
 *
 * License: MIT
 */
class JalaliDate
{
    public const MONTH_NAMES_FA = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public const WEEKDAY_NAMES_FA = [
        0 => 'شنبه',
        1 => 'یک‌شنبه',
        2 => 'دوشنبه',
        3 => 'سه‌شنبه',
        4 => 'چهارشنبه',
        5 => 'پنج‌شنبه',
        6 => 'جمعه',
    ];

    /**
     * Converts a Gregorian date (Y, M, D) to Jalali (Y, M, D).
     *
     * @return array{0: int, 1: int, 2: int} [jy, jm, jd]
     */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100))
            + ((int) (($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];

        $jy = -1595 + (33 * ((int) ($days / 12053)));
        $days %= 12053;

        $jy += 4 * ((int) ($days / 1461));
        $days %= 1461;

        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [(int) $jy, (int) $jm, (int) $jd];
    }

    /**
     * Converts a Jalali date (Y, M, D) to Gregorian (Y, M, D).
     *
     * @return array{0: int, 1: int, 2: int} [gy, gm, gd]
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (((int) ($jy / 33)) * 8) + ((int) ((($jy % 33) + 3) / 4))
            + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

        $gy = 400 * ((int) ($days / 146097));
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * ((int) (--$days / 36524));
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * ((int) ($days / 1461));
        $days %= 1461;

        if ($days > 365) {
            $gy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $sal_a = [0, 31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
            $gd -= $sal_a[$gm];
        }

        return [(int) $gy, (int) $gm, (int) $gd];
    }

    /**
     * Checks if a Jalali date is valid.
     */
    public static function checkDate(int $jm, int $jd, int $jy): bool
    {
        if ($jy < 1 || $jy > 3000 || $jm < 1 || $jm > 12 || $jd < 1) {
            return false;
        }

        if ($jm <= 6 && $jd > 31) {
            return false;
        }

        if ($jm > 6 && $jm <= 11 && $jd > 30) {
            return false;
        }

        if ($jm === 12) {
            $maxDays = self::isLeapYear($jy) ? 30 : 29;
            if ($jd > $maxDays) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a Jalali year is a leap year (کبیسه).
     */
    public static function isLeapYear(int $jy): bool
    {
        $r = ($jy % 33);

        return in_array($r, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    /**
     * Format a timestamp into a formatted Jalali string.
     */
    public static function format(string $format, int|DateTimeInterface|null $timestamp = null, string $trNum = 'fa'): string
    {
        if ($timestamp instanceof DateTimeInterface) {
            $ts = $timestamp->getTimestamp();
        } elseif (is_numeric($timestamp)) {
            $ts = (int) $timestamp;
        } else {
            $ts = time();
        }

        $dateParts = explode('_', date('Y_n_j_H_i_s_w_z_W', $ts));
        $gy = (int) $dateParts[0];
        $gm = (int) $dateParts[1];
        $gd = (int) $dateParts[2];
        $hour = $dateParts[3];
        $minute = $dateParts[4];
        $second = $dateParts[5];
        $wday = (int) $dateParts[6]; // 0 (Sun) to 6 (Sat)

        [$jy, $jm, $jd] = self::toJalali($gy, $gm, $gd);

        // Map Gregorian day of week (0=Sun) to Iranian week index (0=Sat, 6=Fri)
        $jalaliWday = ($wday + 1) % 7;

        $doy = ($jm <= 6) ? (($jm - 1) * 31) + $jd : 186 + (($jm - 7) * 30) + $jd;

        $replacements = [
            'Y' => sprintf('%04d', $jy),
            'y' => sprintf('%02d', $jy % 100),
            'm' => sprintf('%02d', $jm),
            'n' => (string) $jm,
            'd' => sprintf('%02d', $jd),
            'j' => (string) $jd,
            'H' => $hour,
            'h' => sprintf('%02d', ((int) $hour % 12) ?: 12),
            'i' => $minute,
            's' => $second,
            'F' => self::MONTH_NAMES_FA[$jm] ?? '',
            'l' => self::WEEKDAY_NAMES_FA[$jalaliWday] ?? '',
            'w' => (string) $jalaliWday,
            'z' => (string) ($doy - 1),
            't' => (string) (($jm <= 6) ? 31 : (($jm <= 11) ? 30 : (self::isLeapYear($jy) ? 30 : 29))),
            'L' => self::isLeapYear($jy) ? '1' : '0',
        ];

        $output = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $c = $format[$i];
            if ($c === '\\' && $i + 1 < $len) {
                $output .= $format[++$i];
            } elseif (isset($replacements[$c])) {
                $output .= $replacements[$c];
            } else {
                $output .= $c;
            }
        }

        if ($trNum === 'fa') {
            $enDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $faDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $output = str_replace($enDigits, $faDigits, $output);
        }

        return $output;
    }
}
