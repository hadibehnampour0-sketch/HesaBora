<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleLicensed
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! $this->licenseService->isModuleLicensed($module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'module_not_licensed',
                    'module' => $module,
                    'message' => "دسترسی به این قابلیت در پلن لایسنس فعال شما فعال نیست. جهت ارتقا یا خرید ماژول به بخش مدیریت لایسنس مراجعه فرمایید.",
                    'upgrade_url' => route('license.index'),
                ], 403);
            }

            $allModules = $this->licenseService->getAllModules();
            $moduleTitle = $allModules[$module]['title'] ?? $module;

            return redirect()->route('license.index')
                ->with('warning', "دسترسی به بخش «{$moduleTitle}» نیازمند فعال‌سازی این ماژول در لایسنس نرم‌افزار است.")
                ->with('highlight_module', $module);
        }

        return $next($request);
    }
}
