<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppearanceController extends Controller
{
    /**
     * Display the appearance and theme customization settings.
     */
    public function index(Request $request)
    {
        $currentTheme = $request->cookie('dakhl_ui_theme', 'flow');
        $currentAccent = $request->cookie('dakhl_accent_color', 'cyan');
        $currentDensity = $request->cookie('dakhl_ui_density', 'comfortable');
        $currentMode = $request->cookie('dakhl_theme_mode', 'light');

        return view('settings.appearance', [
            'currentTheme' => $currentTheme,
            'currentAccent' => $currentAccent,
            'currentDensity' => $currentDensity,
            'currentMode' => $currentMode,
        ]);
    }

    /**
     * Update user appearance preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ui_theme' => ['required', 'string', 'in:flow,linear,stripe'],
            'accent_color' => ['nullable', 'string', 'in:cyan,indigo,violet,emerald,slate'],
            'ui_density' => ['nullable', 'string', 'in:comfortable,compact'],
            'theme_mode' => ['nullable', 'string', 'in:light,dark'],
        ]);

        $theme = $validated['ui_theme'];
        $accent = $validated['accent_color'] ?? 'cyan';
        $density = $validated['ui_density'] ?? 'comfortable';
        $mode = $validated['theme_mode'] ?? null;

        $sessionData = [
            'dakhl_ui_theme' => $theme,
            'dakhl_accent_color' => $accent,
            'dakhl_ui_density' => $density,
        ];

        if ($mode) {
            $sessionData['dakhl_theme_mode'] = $mode;
        }

        // Persist in session and return cookies that last for 1 year
        session($sessionData);

        $response = redirect()->route('settings.appearance')
            ->withCookie(cookie()->forever('dakhl_ui_theme', $theme))
            ->withCookie(cookie()->forever('dakhl_accent_color', $accent))
            ->withCookie(cookie()->forever('dakhl_ui_density', $density))
            ->with('success', __('پوسته و تنظیمات ظاهری با موفقیت ذخیره و اعمال شد.'));

        if ($mode) {
            $response->withCookie(cookie()->forever('dakhl_theme_mode', $mode));
        }

        return $response;
    }
}
