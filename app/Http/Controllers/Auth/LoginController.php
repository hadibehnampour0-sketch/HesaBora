<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (! app()->environment('testing')) {
            try {
                if (! file_exists(storage_path('installed')) || ! Schema::hasTable('users')) {
                    return redirect()->route('install.index');
                }
            } catch (\Throwable) {
                return redirect()->route('install.index');
            }
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        if (! app()->environment('testing')) {
            try {
                if (! file_exists(storage_path('installed')) || ! Schema::hasTable('users')) {
                    return redirect()->route('install.index');
                }
            } catch (\Throwable) {
                return redirect()->route('install.index');
            }
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = $request->boolean('remember');
        // A Persian keyboard layout types Farsi digits (۰-۹) for numeric
        // keys, so an all-digit username/password (like the seeded default
        // admin password) silently fails to match unless normalized first —
        // the same normalization already applied to the OTP field.
        $identifier = toEnglish(trim($credentials['username']));
        $password = toEnglish($credentials['password']);

        $authenticated = Auth::attempt([
            'username' => $identifier,
            'password' => $password,
        ], $remember);

        // Backwards compatibility: accounts that predate username support log in with their email.
        if (! $authenticated && str_contains($identifier, '@')) {
            $authenticated = Auth::attempt([
                'email' => $identifier,
                'password' => $password,
            ], $remember);
        }

        if (! $authenticated) {
            return back()->withErrors([
                'username' => __('نام کاربری یا کلمه عبور وارد شده نادرست است.'),
            ])->onlyInput('username', 'remember');
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (config('app.email_verification') && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')->with('error', __('Please verify your account before continuing.'));
        }

        if (! $user->companies()->exists() && ! $user->can('access-super-admin-panel')) {
            return redirect()->route('registered-user.company.create');
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate(); // Optional: Invalidate session for added security
        $request->session()->regenerateToken(); // Optional: Regenerate session token for CSRF protection

        return redirect('/login');
    }

    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:fa,en'],
        ]);

        $request->session()->put('locale', $validated['locale']);
        App::setLocale($validated['locale']);

        return back();
    }
}
