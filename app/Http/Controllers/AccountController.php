<?php

namespace App\Http\Controllers;

use App\Enums\UiMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Show the current user's own account settings (username/password).
     */
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Let the current user set their own username and/or password.
     * Requires the current password to confirm identity before any change.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // A Persian keyboard layout types Farsi digits (۰-۹) for numeric
        // keys, so an all-digit password silently fails to match unless
        // normalized first — same normalization already used for login/OTP.
        $currentPassword = toEnglish($validated['current_password']);

        if (! Hash::check($currentPassword, $user->password)) {
            return back()->withErrors([
                'current_password' => __('The provided password does not match your current password.'),
            ])->onlyInput('username');
        }

        // R-01: a user flagged with the public default password must pick a
        // genuinely new one -- not blank, not the same, not the default again.
        if ($user->must_change_password) {
            $newPassword = toEnglish((string) ($validated['password'] ?? ''));

            if ($newPassword === '') {
                return back()->withErrors(['password' => __('You must set a new password.')])->onlyInput('username');
            }

            if ($newPassword === $currentPassword || $newPassword === '1234' || Hash::check($newPassword, $user->password)) {
                return back()->withErrors(['password' => __('The new password must be different from the default password.')])->onlyInput('username');
            }
        }

        $user->username = toEnglish(trim($validated['username']));

        if (filled($validated['password'] ?? null)) {
            $user->password = bcrypt(toEnglish($validated['password']));
            $user->must_change_password = false;
        }

        $user->save();

        Auth::setUser($user->fresh());

        return redirect()->route('account.edit')->with('success', __('Your account was updated successfully.'));
    }

    /**
     * P8.1 (Zero-Knowledge UX). A separate, password-free action --
     * switching display mode is not a security-sensitive change, unlike
     * username/password, so it doesn't belong behind update()'s
     * current-password confirmation.
     */
    public function updateUiMode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ui_mode' => ['required', 'string', Rule::in(UiMode::valueNames())],
        ]);

        $user = $request->user();
        $user->update(['ui_mode' => $validated['ui_mode']]);
        Auth::setUser($user->fresh());

        // The header toggle asks to stay on the current page; the account page form keeps its own redirect.
        $target = $request->input('return') === 'back' ? redirect()->back(fallback: route('home')) : redirect()->route('account.edit');

        return $target->with('success', __('Display mode updated successfully.'));
    }
}
