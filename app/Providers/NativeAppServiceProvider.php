<?php

namespace App\Providers;

use App\Models\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Alert;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);

            // Self-healing: re-seed whenever the admin account is actually
            // missing, not only when the "seeded" flag was never set. A
            // machine can end up with the flag set but no working admin
            // account if a previous run's seeding partially failed midway —
            // db:seed is idempotent (firstOrCreate throughout), so retrying
            // it is always safe and never duplicates/overwrites real data.
            $needsSeeding = ! Schema::hasTable('configs')
                || Config::where('seeded', true)->count() !== 1
                || ! Schema::hasTable('users')
                || \App\Models\User::withoutGlobalScopes()->where('username', 'admin')->doesntExist();

            if ($needsSeeding) {
                Artisan::call('db:seed', ['--force' => true]);
            }

            // If it's still missing after a fresh seeding attempt, the
            // failure is real (not just an unset flag) — surface it instead
            // of silently letting the user reach a login screen that can
            // never succeed, with no indication why.
            if (\App\Models\User::withoutGlobalScopes()->where('username', 'admin')->doesntExist()) {
                throw new \RuntimeException('First-run setup did not create the default admin account.');
            }
        } catch (\Throwable $e) {
            logger('Database error: '.$e->getMessage());

            Alert::error(
                __('Setup Failed'),
                "Dakhl could not finish setting up its local database.\n\n".
                    'Error: '.$e->getMessage()."\n\n".
                    'Please reopen the app to retry. If this keeps happening, send this message to support.'
            );
        }

        Window::open()
        ->maximized() // Opens the window in full screen on launch
        ->minWidth(1024)
        ->minHeight(768)
        ->showDevTools(false)
        ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
              'memory_limit' => '512M',
            'display_errors' => '1',
            'error_reporting' => 'E_ALL',
            'max_execution_time' => '0',
            'max_input_time' => '0',
        ];
    }
}
