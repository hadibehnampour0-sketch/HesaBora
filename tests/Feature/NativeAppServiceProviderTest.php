<?php

namespace Tests\Feature;

use App\Providers\NativeAppServiceProvider;
use Tests\TestCase;

/**
 * Release Readiness Audit finding: phpIni() previously hardcoded
 * display_errors=1/error_reporting=E_ALL unconditionally, meaning a
 * packaged production desktop build would render a raw PHP error (file
 * paths, SQL, stack traces) directly to an end user. Fixed to tie
 * display_errors to config('app.debug') -- the same flag the web path
 * already relies on (and which .env.example already defaults to false)
 * -- while keeping error_reporting/log_errors on unconditionally so
 * nothing is silently lost, just redirected to the log instead of the
 * screen.
 */
class NativeAppServiceProviderTest extends TestCase
{
    public function test_display_errors_is_disabled_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $phpIni = (new NativeAppServiceProvider)->phpIni();

        $this->assertSame('0', $phpIni['display_errors']);
        $this->assertSame('1', $phpIni['log_errors']);
    }

    public function test_display_errors_is_enabled_when_debug_is_on(): void
    {
        config(['app.debug' => true]);

        $phpIni = (new NativeAppServiceProvider)->phpIni();

        $this->assertSame('1', $phpIni['display_errors']);
        $this->assertSame('1', $phpIni['log_errors']);
    }

    public function test_error_reporting_stays_on_regardless_of_debug_mode(): void
    {
        config(['app.debug' => false]);
        $prod = (new NativeAppServiceProvider)->phpIni();

        config(['app.debug' => true]);
        $dev = (new NativeAppServiceProvider)->phpIni();

        $this->assertSame('E_ALL', $prod['error_reporting']);
        $this->assertSame('E_ALL', $dev['error_reporting']);
    }
}
