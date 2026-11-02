<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DefaultCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasCookie('active-company-id')) {
            $company = Company::find($request->cookie('active-company-id'));

            if (! $company or ! $company->users->contains(auth()->id())) {
                Cookie::forget('active-company-id');

                config([
                    'active-company-id' => null,
                    'active-company-name' => null,
                    'active-company-fiscal-year' => null,
                ]);

                $this->setDefaultCompany();
            } else {
                $this->activateCompany($company);
            }
        } else {
            $this->setDefaultCompany();
        }

        // Security (D-136, Fail-Closed): When an authenticated user requests a workspace route
        // without a valid active company context, fail closed.
        if (Auth::check() && config('active-company-id') === null) {
            if ($this->requiresActiveCompany($request)) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => __('No active company context. A valid company must be selected or provided.'),
                    ], 422);
                }

                return redirect()->route('companies.index')
                    ->with('error', __('Please select or create an active company to continue.'));
            }
        }

        return $next($request);
    }

    private function setDefaultCompany(): void
    {
        if (Auth::check()) {
            $company = Auth::user()->companies()->where('fiscal_year', toEnglish(jdate('Y')))->first()
                ?? Auth::user()->companies()->first();
            if ($company) {
                Cookie::queue('active-company-id', $company->id, 362 * 24 * 60);

                $this->activateCompany($company);
            }
        }
    }

    private function activateCompany(Company $company): void
    {
        config([
            'active-company-id' => $company->id,
            'active-company-name' => $company->name,
            'active-company-fiscal-year' => $company->fiscal_year,
        ]);
    }

    private function requiresActiveCompany(Request $request): bool
    {
        if ($request->is('test/*') || str_starts_with($request->path(), 'test/')) {
            return false;
        }

        if ($request->is('docs/*') || $request->is('docs') || $request->is('_scramble/*')) {
            return false;
        }

        $routeName = $request->route()?->getName() ?? '';

        $exemptPatterns = [
            'companies.*',
            'change-company',
            'users.*',
            'account.*',
            'management.*',
            'update-global-configs',
            'logout',
            'impersonation.*',
            'about',
            'locale',
            'portal.*',
            'pricing',
            'logo.showcase',
            'pages.showcase',
            'password.*',
            'verification.*',
            'scramble.*',
            'api-docs.*',
        ];

        foreach ($exemptPatterns as $pattern) {
            if (fnmatch($pattern, $routeName)) {
                return false;
            }
        }

        return true;
    }
}
