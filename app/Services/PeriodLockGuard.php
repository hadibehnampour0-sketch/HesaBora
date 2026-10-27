<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Single choke-point guard protecting accounting posting paths against closed/locked periods.
 * Enforces Phase F (Period Close / D-128 / D-042).
 */
class PeriodLockGuard
{
    /**
     * Assert that the given company's fiscal period permits mutations.
     * Throws ValidationException if locked or closed without override.
     *
     *
     * @throws ValidationException
     */
    public static function assertMutable(int|Company|null $company, ?string $action = null): void
    {
        if (is_null($company)) {
            $companyId = getActiveCompany();
            if (! $companyId) {
                return;
            }
            $company = Company::find($companyId);
        } elseif (is_numeric($company)) {
            $company = Company::find($company);
        }

        if (! $company) {
            return;
        }

        $status = $company->period_status ?? (
            $company->closed_at !== null ? PeriodStatus::CLOSED : PeriodStatus::OPEN
        );

        if ($status === PeriodStatus::LOCKED) {
            $msg = 'دوره مالی این شرکت در وضعیت قفل قطعی (Locked) قرار دارد و هرگونه ثبت، ویرایش، حذف یا ابطال سند مالی در آن مسدود است.';
            if ($action) {
                $msg .= " (عملیات مسدود شده: {$action})";
            }
            throw ValidationException::withMessages([
                'period_status' => [$msg],
            ]);
        }

        if ($status === PeriodStatus::CLOSED) {
            $user = Auth::user();
            $hasOverride = $user && ($user->can('access-super-admin-panel') || $user->can('fiscal-year.closed-period-override'));

            if (! $hasOverride) {
                $msg = 'این دوره مالی بسته شده است (Closed) و ثبت یا تغییر تراکنش در آن نیازمند مجوز ارشد است.';
                if ($action) {
                    $msg .= " (عملیات: {$action})";
                }
                throw ValidationException::withMessages([
                    'period_status' => [$msg],
                ]);
            }
        }
    }

    /**
     * Check if mutations are allowed without throwing an exception.
     */
    public static function isMutable(int|Company|null $company): bool
    {
        try {
            self::assertMutable($company);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
