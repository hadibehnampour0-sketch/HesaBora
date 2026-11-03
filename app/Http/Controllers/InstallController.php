<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class InstallController extends Controller
{
    private const REQUIRED_EXTENSIONS = [
        'pdo',
        'openssl',
        'mbstring',
        'tokenizer',
        'xml',
        'ctype',
        'json',
        'bcmath',
        'fileinfo',
    ];

    public function index(): View|RedirectResponse
    {
        if ($this->isAlreadyInstalled()) {
            return redirect()->route('login')->with('info', __('سامانه دخل قبلاً نصب و راه‌اندازی شده است.'));
        }

        $requirements = $this->getRequirementsStatus();
        $allPassed = ! in_array(false, array_column($requirements['extensions'], 'passed'), true)
            && ! in_array(false, array_column($requirements['directories'], 'passed'), true)
            && $requirements['php']['passed'];

        return view('install.wizard', [
            'requirements' => $requirements,
            'allPassed' => $allPassed,
            'dbConnected' => $this->isDatabaseConnected(),
        ]);
    }

    public function migrate(): JsonResponse
    {
        if ($this->isAlreadyInstalled()) {
            return response()->json(['success' => false, 'message' => __('سامانه قبلاً نصب شده است.')], 403);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);

            return response()->json([
                'success' => true,
                'message' => __('جداول پایگاه داده با موفقیت ایجاد و به‌روزرسانی شدند.'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('خطا در اجرای مایگریشن‌ها: ').$e->getMessage(),
            ], 500);
        }
    }

    public function seed(): JsonResponse
    {
        if ($this->isAlreadyInstalled()) {
            return response()->json(['success' => false, 'message' => __('سامانه قبلاً نصب شده است.')], 403);
        }

        try {
            // Seed essential baseline permissions and roles (without demo users)
            (new RolesAndPermissionsSeeder)->seedPermissionsAndRoles();

            return response()->json([
                'success' => true,
                'message' => __('اطلاعات پایه و سطوح دسترسی با موفقیت ثبت شدند.'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('خطا در بارگذاری اطلاعات پایه: ').$e->getMessage(),
            ], 500);
        }
    }

    public function setupAdmin(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->isAlreadyInstalled()) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'company_name' => 'required|string|max:191',
            'admin_name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            DB::beginTransaction();

            // 1. Create Default Company
            $company = Company::firstOrCreate(
                ['name' => $validated['company_name']],
                [
                    'uuid' => (string) Str::uuid(),
                    'fiscal_year' => 1405,
                ]
            );

            // 2. Create Default Fiscal Year
            if (Schema::hasTable('fiscal_years')) {
                DB::table('fiscal_years')->updateOrInsert(
                    [
                        'company_id' => $company->id,
                        'year_number' => 1405,
                    ],
                    [
                        'uuid' => (string) Str::uuid(),
                        'start_date' => '2026-03-21',
                        'end_date' => '2027-03-20',
                        'status' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            // 3. Seed Subjects & Configs for this company
            config(['active-company-id' => $company->id]);
            (new SubjectSeeder)->run($company->id);
            (new ConfigSeeder)->run($company->id);

            // 4. Create Super Admin User
            $admin = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['admin_name'],
                    'password' => Hash::make($validated['password']),
                    'company_id' => $company->id,
                ]
            );

            if (method_exists($admin, 'companies')) {
                $admin->companies()->syncWithoutDetaching([$company->id]);
            }

            // 5. Assign Admin Role
            if (method_exists($admin, 'assignRole')) {
                try {
                    $admin->assignRole('Super-Admin');
                } catch (\Throwable) {
                    try {
                        $admin->assignRole(__('Admin'));
                    } catch (\Throwable) {
                        // ignore
                    }
                }
            }

            // 6. Lock Installer
            $this->lockInstallation();

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('نصب سامانه دخل با موفقیت پایان یافت.'),
                    'redirect' => route('login'),
                ]);
            }

            return redirect()->route('login')->with('success', __('نصب سامانه با موفقیت انجام شد. اکنون وارد شوید.'));
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('خطا در ثبت اطلاعات مدیر و شرکت: ').$e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function isAlreadyInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    private function lockInstallation(): void
    {
        file_put_contents(storage_path('installed'), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => '1.0.0-cleanroom',
        ], JSON_PRETTY_PRINT));
    }

    private function isDatabaseConnected(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function getRequirementsStatus(): array
    {
        $phpVersion = PHP_VERSION;
        $phpPassed = version_compare($phpVersion, '8.2.0', '>=');

        $extensions = [];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $extensions[$ext] = [
                'name' => $ext,
                'passed' => extension_loaded($ext),
            ];
        }

        $directories = [
            'storage' => [
                'path' => storage_path(),
                'passed' => is_writable(storage_path()),
            ],
            'storage/framework' => [
                'path' => storage_path('framework'),
                'passed' => is_writable(storage_path('framework')),
            ],
            'storage/logs' => [
                'path' => storage_path('logs'),
                'passed' => is_writable(storage_path('logs')),
            ],
            'bootstrap/cache' => [
                'path' => base_path('bootstrap/cache'),
                'passed' => is_writable(base_path('bootstrap/cache')),
            ],
        ];

        return [
            'php' => [
                'version' => $phpVersion,
                'passed' => $phpPassed,
            ],
            'extensions' => $extensions,
            'directories' => $directories,
        ];
    }
}
