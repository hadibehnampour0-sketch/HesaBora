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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $activeLicense = $this->licenseService->getActiveLicense();

        // Non-destructive read-only enforcement upon expiration (D-024)
        if ($activeLicense->isPastGracePeriod()) {
            if (! $request->isMethodSafe()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'license_expired_read_only',
                        'message' => 'اعتبار لایسنس شما منقضی شده است. سیستم در وضعیت فقط-خواندنی قرار دارد و ثبت یا ویرایش داده‌های جدید مسدود است.',
                    ], 403);
                }

                return redirect()->route('license.index')
                    ->with('error', 'اعتبار لایسنس شما منقضی شده است. سیستم در وضعیت فقط-خواندنی (Read-Only) قرار دارد. داده‌های قبلی شما کاملاً امن، پایدار و قابل مشاهده هستند.');
            }

            // Safe requests (GET, HEAD, OPTIONS) are allowed if the module was entitled
            if ($activeLicense->hasModule($module, allowExpiredReadOnly: true)) {
                return $next($request);
            }
        }

        if (! $this->licenseService->isModuleLicensed($module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'module_not_licensed',
                    'module' => $module,
                    'message' => 'دسترسی به این قابلیت در پلن لایسنس فعال شما فعال نیست. جهت ارتقا یا خرید ماژول به بخش مدیریت لایسنس مراجعه فرمایید.',
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
