<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OrganizationUnit;
use App\Models\OrgChart;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Standard RESTful resource actions.
     */
    private const CRUD = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    /**
     * RESTful resource actions without `show`.
     */
    private const CRUD_NO_SHOW = ['index', 'create', 'store', 'edit', 'update', 'destroy'];

    public function run(): void
    {
        $this->seedPermissionsAndRoles();
        $this->seedDemoUsersAndEmployees();
    }

    public function seedPermissionsAndRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->definePermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->seedRoles();
    }

    /**
     * Build the full flat list of permissions from the grouped definition.
     *
     * Each entry of `$groups` is `prefix => actions[]`. The resulting permission
     * name is `"{$prefix}.{$action}"`. Free-standing permissions (not tied to a
     * RESTful resource) are listed under `extras`.
     *
     * @return array<int, string>
     */
    private function definePermissions(): array
    {
        $groups = [
            // Subjects / accounting subjects
            'subjects' => [...self::CRUD, 'search', 'search-code', 'transfer'],

            // Documents (accounting documents) and their files
            'documents' => [
                ...self::CRUD,
                'print', 'duplicate', 'change-status', 'approve-all',
                'search-account-balance', 'export', 'export.download', 'import', 'import.store',
                'sort-numbers', 'sort-numbers.start', 'sort-numbers.process', 'transfer',
            ],
            'documents.files' => ['create', 'store', 'edit', 'update', 'destroy', 'view', 'download'],

            'transactions' => ['index', 'show'],

            // Products / services and their groups
            'products' => [...self::CRUD, 'search-product-group', 'report', 'export', 'recalculate-quantity'],
            'product-groups' => self::CRUD,
            'services' => [...self::CRUD, 'search-service-group'],
            'service-groups' => self::CRUD,

            // Customers
            'customers' => [...self::CRUD, 'export', 'import', 'import.store'],
            'customer-groups' => self::CRUD,

            // CRM
            'crm' => ['dashboard'],

            // Companies + fiscal-year wizard
            'companies' => [
                ...self::CRUD,
                'close-fiscal-year',
                'closing-wizard', 'closing-wizard.step1', 'closing-wizard.step3',
            ],

            // P6 (API v1 Platform): outbound webhook registrations.
            'webhooks' => self::CRUD_NO_SHOW,

            // P7 (Role & Workflow Governance): the self-approval-prevention
            // toggle and the invoice approval-chain threshold.
            'workflow-governance' => ['edit', 'update'],

            // P9.6 (Evidence Vault).
            'tax-evidences' => ['store', 'download', 'destroy'],

            // P9.7 (Tax Readiness Score).
            'tax-readiness' => ['index', 'recalculate'],

            // P12.2 (Tax Alert & Smart Notification Engine).
            'tax-alerts' => ['index', 'read', 'read-all'],

            // P12.3 (Seasonal Tax & VAT Reporting Engine).
            'seasonal-tax' => ['index', 'export-csv', 'export-pdf'],

            // P12.4 (Field Operations Commission & Route Analytics Engine).
            'field-operations-analytics' => ['index', 'recalculate'],

            // Industry Profiles (per-trade settings + calculators).
            'industry-profiles' => ['index', 'show', 'update', 'toggle', 'confirm', 'calculate'],

            // P11 (Field Sales & Distribution -- "FieldOperations").
            'sales-routes' => [...self::CRUD_NO_SHOW, 'assign-customer', 'remove-customer'],
            'visit-plans' => ['index', 'store', 'update-status', 'destroy'],
            'promotions' => [...self::CRUD_NO_SHOW, 'evaluate'],
            'daily-settlements' => ['index', 'store', 'submit', 'approve'],

            // Backups
            'backups' => ['create', 'export', 'import', 'upload', 'document-files-size'],

            // Banks / bank accounts
            'bank-accounts' => [...self::CRUD, 'search-bank'],
            'banks' => self::CRUD,
            'cheques' => [...self::CRUD, 'report', 'transition'],
            'chequebooks' => self::CRUD,

            // Invoices and their statuses
            'invoices' => [
                ...self::CRUD,
                'dashboard', 'print', 'export', 'change-status', 'search', 'approve', 'final-approve', 'get-items',
                'moadian-form', 'moadian-histories.index', 'moadian-histories.show', 'send-moadian', 'moadian-check-status',
                'search-customer', 'search-product-service',
                'inactive', 'inactive.approve',
                'conflicts', 'conflicts.more',
                'group-action', 'void-form', 'void', 'transfer',
            ],

            // Ancillary costs
            'ancillary-costs' => ['index', 'search-customer', 'search-invoice', 'get-products', 'approve', 'change-status', 'set-tax-deductible-status'],
            'invoices.ancillary-costs' => ['create', 'store', 'show', 'edit', 'update', 'destroy'],

            // Invoice payments
            'invoices.payments' => ['store', 'store-cheque', 'destroy', 'create-document', 'destroy-document'],

            // Payment allocation (advance/credit payments allocated across invoices)
            'customers.credit-payments' => ['store'],
            'payments.allocations' => ['store', 'destroy'],

            // Users / Roles / Permissions / Configs
            'users' => [...self::CRUD, 'create-employee', 'impersonate'],
            'permissions' => self::CRUD_NO_SHOW,
            'roles' => self::CRUD_NO_SHOW,
            'configs' => self::CRUD,

            // HR
            'hr.employees' => [...self::CRUD, 'export'],
            'hr.org-charts' => self::CRUD,
            'hr.organization-units' => self::CRUD,
            'hr.personnel-requests' => [...self::CRUD, 'approve', 'reject'],

            // Attendance
            'attendance.attendance-logs' => [
                ...self::CRUD,
                'recalculate', 'recalculate-all',
                'import', 'import.preview', 'import.store',
                'bulk-create', 'bulk-store', 'search-employee',
            ],
            'attendance.monthly-attendances' => [...self::CRUD, 'recalculate', 'payroll.store', 'bulk-create', 'bulk-store'],
            'attendance.work-shifts' => self::CRUD_NO_SHOW,

            // Salary / Payroll
            'salary.tax-slabs' => self::CRUD,
            'salary.work-sites' => self::CRUD_NO_SHOW,
            'salary.work-site-contracts' => self::CRUD_NO_SHOW,
            'salary.public-holidays' => self::CRUD_NO_SHOW,
            'salary.payroll-elements' => self::CRUD_NO_SHOW,
            'salary.salary-decrees' => self::CRUD_NO_SHOW,
            'salary.payrolls' => [
                'index', 'show', 'destroy', 'dashboard',
                'transition.draft-to-pending-manager-approval',
                'transition.pending-manager-approval-to-approved',
                'transition.approved-to-paid',
            ],
            'salary.payroll-items' => ['edit', 'update'],

            // Warehouse
            'warehouse' => ['dashboard'],
            'warehouses' => [...self::CRUD_NO_SHOW, 'transfer', 'transfer.store', 'transfer-history'],

            // Budgeting
            'budgets' => ['index', 'store', 'rollover', 'calculate-expenses', 'destroy', 'search-subjects'],

            // Comments
            'comments' => self::CRUD_NO_SHOW,

            // Reports
            'reports' => ['ledger', 'journal', 'sub-ledger', 'trial-balance', 'trial-balance.print', 'trial-balance.export-csv', 'documents', 'result', 'cost-income', 'company-overview', 'company-overview.cash-banks', 'company-overview.bank-account', 'company-overview.seed-demo-data', 'company-overview.refresh-database'],

            // AR/AP ageing
            'ageing' => ['receivables', 'payables', 'statement'],

            // Bank reconciliation
            'bank-reconciliation' => ['index', 'import', 'match', 'unmatch'],

            // CheckSettlement (D-070)
            'settlements' => ['store-for-cheque', 'show'],
            'settlements.installments' => ['store', 'record-payment'],

            // Device pairing / Offline sync foundation (D-071)
            'devices' => ['index', 'revoke'],
            'devices.activation-codes' => ['store'],

            // Conflict Resolution Workflow (D-072)
            'sync-conflicts' => ['index', 'resolve'],

            // API
            'api-tokens' => ['index', 'create', 'store', 'destroy'],

            // Home
            'home' => ['summary', 'today'],

            // Employee portal
            'employee-portal' => [
                'employee.show',
                'change-employee-information', 'update-employee-information',
                'dashboard',
                'attendance-logs',
                'monthly-attendances', 'monthly-attendances.show',
                'payrolls', 'payrolls.show',
            ],
            'employee-portal.personnel-requests' => self::CRUD_NO_SHOW,
        ];

        $extras = [
            'home',
            'access-super-admin-panel',
            'activity-logs.index',
            'activity-logs.details',
            'api.access',
            'change-company',
            'update-global-configs',
            'report',
        ];

        $permissions = $extras;
        foreach ($groups as $prefix => $actions) {
            foreach ($actions as $action) {
                $permissions[] = $prefix.'.'.$action;
            }
        }

        return array_values(array_unique($permissions));
    }

    private function seedRoles(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'Super-Admin']);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => __('Admin')]);
        $admin->syncPermissions(
            Permission::query()
                ->where('name', 'NOT LIKE', 'roles.%')
                ->where('name', 'NOT LIKE', 'permissions.%')
                ->where('name', '!=', 'access-super-admin-panel')
                ->where('name', '!=', 'update-global-configs')
                ->pluck('name')
                ->toArray()
        );

        $superAdmin->users()->each(fn (User $user) => $user->assignRole($admin));

        $accountant = Role::firstOrCreate(['name' => __('Accountant')]);
        $accountant->syncPermissions(
            Permission::query()
                ->where(fn ($query) => $query
                    ->where('name', 'NOT LIKE', 'users.%')
                    ->orWhereIn('name', ['users.index', 'users.impersonate']))
                ->where('name', 'NOT LIKE', 'roles.%')
                ->where('name', 'NOT LIKE', 'permissions.%')
                ->where('name', 'NOT LIKE', 'configs.%')
                ->where('name', '!=', 'access-super-admin-panel')
                ->where('name', '!=', 'update-global-configs')
                ->where('name', 'NOT LIKE', 'api.%')
                ->where('name', 'NOT LIKE', 'api-tokens.%')
                ->where('name', '!=', 'salary.payrolls.transition.pending-manager-approval-to-approved')
                ->pluck('name')
                ->toArray()
        );

        $warehouse = Role::firstOrCreate(['name' => __('Warehousekeeper')]);
        $warehouse->syncPermissions(
            Permission::query()
                ->where(fn ($q) => $q
                    ->where('name', 'LIKE', 'products.%')
                    ->orWhere('name', 'LIKE', 'product-groups.%')
                    ->orWhere('name', 'LIKE', 'warehouse.%')
                    ->orWhere('name', 'LIKE', 'home%'))
                ->pluck('name')
                ->toArray()
        );

        $seller = Role::firstOrCreate(['name' => __('Seller')]);
        $seller->syncPermissions(
            Permission::query()
                ->where(fn ($q) => $q
                    ->where('name', 'LIKE', 'invoices.%')
                    ->orWhere('name', 'LIKE', 'ancillary-costs.%')
                    ->orWhere('name', 'LIKE', 'customers.%')
                    ->orWhere('name', 'LIKE', 'customer-groups.%')
                    ->orWhere('name', 'LIKE', 'crm.%')
                    ->orWhereIn('name', ['home', 'home.summary', 'home.today']))
                ->whereNotIn('name', [
                    'invoices.approve',
                    'invoices.final-approve',
                    'invoices.change-status',
                    'invoices.conflicts',
                    'invoices.conflicts.more',
                    'invoices.group-action',
                    'invoices.inactive',
                    'invoices.inactive.approve',
                    'invoices.transfer',
                    'ancillary-costs.approve',
                    'ancillary-costs.change-status',
                    'ancillary-costs.set-tax-deductible-status',
                    'invoices.payments.store',
                    'invoices.payments.store-cheque',
                    'invoices.payments.destroy',
                    'invoices.payments.create-document',
                    'invoices.payments.destroy-document',
                ])
                ->pluck('name')
                ->toArray()
        );

        $employeeRole = Role::firstOrCreate(['name' => __('Employee')]);
        $employeeRole->syncPermissions(
            Permission::query()
                ->where('name', 'LIKE', 'employee-portal.%')
                ->orWhere('name', '=', 'change-company')
                ->orWhereIn('name', ['home', 'home.summary', 'home.today'])
                ->orWhere('name', '=', 'attendance.attendance-logs.show')
                ->pluck('name')
                ->toArray()
        );
    }

    private function seedDemoUsersAndEmployees(): void
    {
        $companyId = (int) getActiveCompany();
        $users = [
            'super-admin' => [
                'roles' => ['Super-Admin', __('Admin'), __('Employee')],
                'org_chart' => 'مدیرعامل',
                'org_unit' => 'مدیریت',
            ],
        ];

        $workSite = WorkSite::firstOrCreate(
            ['company_id' => $companyId, 'code' => 'DEMO-WS-1'],
            [
                'name' => 'کارگاه ۱',
                'is_active' => true,
            ]
        );

        $workShift = WorkShift::firstOrCreate(
            [
                'company_id' => $companyId,
                'name' => 'شیفت کاری',
            ],
            [
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'float' => 0,
                'break' => 0,
                'is_active' => true,
                'paid_leave' => 1200,
            ]
        );

        $orgCharts = OrgChart::withoutGlobalScopes()->where('company_id', $companyId)->get()->keyBy('title');
        $orgUnits = OrganizationUnit::withoutGlobalScopes()->where('company_id', $companyId)->get()->keyBy('name');

        foreach ($users as $name => $config) {
            // SECURITY: intentionally simple/memorable per owner request
            // (2026-09-14) so every fresh install has an easy first login.
            // This is a known, public default baked into open-source code,
            // not a secret. See CLOUD_AUTONOMOUS_EXECUTION_LOG.md (P0-02) for
            // the open follow-up to force a password change on first login
            // before any public release, since "1234" must never remain the
            // active password on a real deployment.
            $email = 'admin@dakhl.local';
            $username = 'admin';
            $password = '1234';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => __($name),
                    'username' => $username,
                    'password' => bcrypt($password),
                    // R-01: the default password is public; force a change on first login.
                    'must_change_password' => true,
                ]
            );

            // Backfill username for accounts created before username support.
            if ($user->username === null) {
                $user->forceFill(['username' => $username])->save();
            }

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->companies()->syncWithoutDetaching([$companyId]);
            $user->assignRole($config['roles']);

            $baseCode = 'EMP-'.$user->id;
            $employeeCode = $baseCode;
            $counter = 1;

            while (Employee::withoutGlobalScopes()
                ->where('code', $employeeCode)
                ->where('user_id', '!=', $user->id)
                ->exists()) {
                $employeeCode = substr($baseCode.'-'.$counter, 0, 20);
                $counter++;
            }

            Employee::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id, 'company_id' => $companyId],
                [
                    'first_name' => __($name),
                    'last_name' => __('Demo'),
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'code' => $employeeCode,
                    'work_site_id' => $workSite->id,
                    'work_shift_id' => $workShift->id,
                    'org_chart_id' => $orgCharts->get($config['org_chart'])?->id,
                    'organization_unit_id' => $orgUnits->get($config['org_unit'])?->id,
                    'is_active' => true,
                    'leave_remain' => 1200,
                ]
            );
        }
    }
}
