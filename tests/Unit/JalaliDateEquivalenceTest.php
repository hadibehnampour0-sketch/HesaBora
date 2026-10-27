<?php

namespace Tests\Unit;

use App\Support\JalaliDate;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class JalaliDateEquivalenceTest extends TestCase
{
    /**
     * Test round-trip conversion accuracy across centuries (1200 to 1500).
     */
    public function test_round_trip_conversion_1200_to_1500(): void
    {
        // Sample years across every century and test key dates
        $sampleYears = [1200, 1250, 1300, 1348, 1357, 1370, 1390, 1400, 1402, 1403, 1404, 1405, 1410, 1450, 1500];

        foreach ($sampleYears as $jy) {
            foreach ([1, 6, 7, 11, 12] as $jm) {
                $days = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : (JalaliDate::isLeapYear($jy) ? 30 : 29));
                foreach ([1, 15, $days] as $jd) {
                    [$gy, $gm, $gd] = JalaliDate::toGregorian($jy, $jm, $jd);
                    [$backJy, $backJm, $backJd] = JalaliDate::toJalali($gy, $gm, $gd);

                    $this->assertEquals(
                        [$jy, $jm, $jd],
                        [$backJy, $backJm, $backJd],
                        "Round-trip failed for Jalali date: {$jy}/{$jm}/{$jd} (Gregorian: {$gy}-{$gm}-{$gd})"
                    );
                }
            }
        }
    }

    /**
     * Test known historical anchor dates against astronomical truth.
     */
    public function test_known_historical_anchor_dates(): void
    {
        $anchors = [
            // Nowruz 1400 (March 21, 2021)
            ['gy' => 2021, 'gm' => 3, 'gd' => 21, 'jy' => 1400, 'jm' => 1, 'jd' => 1],
            // Nowruz 1403 (March 20, 2024 - leap year)
            ['gy' => 2024, 'gm' => 3, 'gd' => 20, 'jy' => 1403, 'jm' => 1, 'jd' => 1],
            // Nowruz 1404 (March 21, 2025)
            ['gy' => 2025, 'gm' => 3, 'gd' => 21, 'jy' => 1404, 'jm' => 1, 'jd' => 1],
            // End of Esfand 1403 (March 20, 2025 - day 30 in leap year)
            ['gy' => 2025, 'gm' => 3, 'gd' => 20, 'jy' => 1403, 'jm' => 12, 'jd' => 30],
            // First of Dey 1403 (December 21, 2024)
            ['gy' => 2024, 'gm' => 12, 'gd' => 21, 'jy' => 1403, 'jm' => 10, 'jd' => 1],
        ];

        foreach ($anchors as $a) {
            $computed = JalaliDate::toJalali($a['gy'], $a['gm'], $a['gd']);
            $this->assertEquals(
                [$a['jy'], $a['jm'], $a['jd']],
                $computed,
                "Gregorian {$a['gy']}-{$a['gm']}-{$a['gd']} expected {$a['jy']}/{$a['jm']}/{$a['jd']}"
            );

            $back = JalaliDate::toGregorian($a['jy'], $a['jm'], $a['jd']);
            $this->assertEquals([$a['gy'], $a['gm'], $a['gd']], $back);
        }
    }

    /**
     * Test leap year detection.
     */
    public function test_leap_years(): void
    {
        $this->assertTrue(JalaliDate::isLeapYear(1399));
        $this->assertTrue(JalaliDate::isLeapYear(1403));
        $this->assertFalse(JalaliDate::isLeapYear(1400));
        $this->assertFalse(JalaliDate::isLeapYear(1401));
        $this->assertFalse(JalaliDate::isLeapYear(1402));
        $this->assertFalse(JalaliDate::isLeapYear(1404));
    }

    /**
     * Test formatting functions and global helpers.
     */
    public function test_formatting_and_global_helpers(): void
    {
        $dt = Carbon::create(2025, 3, 21, 14, 30, 0, 'Asia/Tehran');

        // Test global gregorian_to_jalali
        [$jy, $jm, $jd] = gregorian_to_jalali(2025, 3, 21);
        $this->assertEquals([1404, 1, 1], [$jy, $jm, $jd]);

        // Test string delimiter mode
        $str = gregorian_to_jalali(2025, 3, 21, '/');
        $this->assertEquals('1404/01/01', $str);

        // Test jalali_to_gregorian
        [$gy, $gm, $gd] = jalali_to_gregorian(1404, 1, 1);
        $this->assertEquals([2025, 3, 21], [$gy, $gm, $gd]);

        // Test jdate formatting
        $formattedFa = jdate('Y/m/d', $dt->timestamp, '', 'Asia/Tehran', 'fa');
        $this->assertEquals('۱۴۰۴/۰۱/۰۱', $formattedFa);

        $formattedEn = jdate('Y/m/d', $dt->timestamp, '', 'Asia/Tehran', 'en');
        $this->assertEquals('1404/01/01', $formattedEn);

        // Test month name
        $monthFa = jdate('F', $dt->timestamp);
        $this->assertEquals('فروردین', $monthFa);

        // Test jcheckdate
        $this->assertTrue(jcheckdate(1, 31, 1404));
        $this->assertFalse(jcheckdate(1, 32, 1404));
        $this->assertTrue(jcheckdate(12, 30, 1403)); // leap year
        $this->assertFalse(jcheckdate(12, 30, 1404)); // non-leap year
    }
}
