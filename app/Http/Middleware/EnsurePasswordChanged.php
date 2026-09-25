<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-01. A user flagged `must_change_password` (the seeded default admin whose
 * password is a public default) can only reach the account page (to change
 * it), logout, locale switching and impersonation exit until they do.
 * Web-session only: API tokens and device pairing are created from this web
 * UI, so blocking here also blocks minting new credentials.
 */
class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = [
        'account.edit',
        'account.update',
        'logout',
        'locale',
        'impersonation.leave',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $route = $request->route();

        if ($route && in_array($route->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        $message = __('You must change the default password before continuing.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('account.edit')->with('warning', $message);
    }
}
