# Security & Permission Matrix — Dakhl

**Generated:** 2026-09-21, from `php artisan route:list --json` (Workstream J closure batch). **Scope:** every registered application route (454 total; 467 raw minus 13 framework/debug routes -- `_debugbar`, `_ignition`, `_native`, `sanctum`).

## How to read this matrix

The `Effective Permission` column is computed mechanically from each route's own middleware stack, not re-derived by re-reading controller bodies:

- **`App\Http\Middleware\CheckPermission`** (no explicit parameter) — the required Spatie permission is the route's OWN NAME (or its wildcard, e.g. route `invoices.store` requires permission `invoices.store` OR `invoices.*`). This is `CheckPermission::handle()`'s own documented behavior (`app/Http/Middleware/CheckPermission.php:28-31`) — the permission is never independently specified per-route because the route name IS the permission key by convention.
- **`App\Http\Middleware\CheckPermission:<explicit.permission>`** — an explicit override where the required permission differs from the route name.
- **`Spatie\Permission\Middleware\PermissionMiddleware:<permission>`** — Spatie's own stock middleware, used for a small number of routes (system-configuration screens gated behind `access-super-admin-panel`).
- **`AuthenticateDevice`** — not a Spatie permission at all; a device-credential gate (D-071 decision 6, hashed device key comparison).
- **`EnsureEmployee`** — the employee self-service portal's own gate (an Employee acting on their own attendance/requests, not a company-wide permission).
- **AUTH-ONLY** — protected by login (`Authenticate`) but no permission/role/employee gate beyond that. Every one of these was individually reviewed (see §2) and confirmed to be a route any authenticated user legitimately needs regardless of role (self-service account settings, email verification, onboarding).
- **PUBLIC** — no authentication middleware at all. Every one of these was individually reviewed (see §3).

## §1. Full route-by-route matrix, grouped by domain

### `(root)` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `/` | home | `HomeController@index` | home OR home.* |

### `about` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `about` | about | `AboutController@index` | NONE — auth only |

### `account` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `account` | account.edit | `AccountController@edit` | NONE — auth only |
| PUT | `account` | account.update | `AccountController@update` | NONE — auth only |

### `ageing` (3 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `ageing/payables` | ageing.payables | `AgeingController@payables` | ageing.payables OR ageing.* |
| GET | `ageing/receivables` | ageing.receivables | `AgeingController@receivables` | ageing.receivables OR ageing.* |
| GET | `ageing/statement/{customer}/{type}` | ageing.statement | `AgeingController@statement` | ageing.statement OR ageing.* |

### `api-tokens` (4 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `api-tokens` | api-tokens.index | `ApiTokenController@index` | api-tokens.index OR api-tokens.* |
| POST | `api-tokens` | api-tokens.store | `ApiTokenController@store` | api-tokens.store OR api-tokens.* |
| GET | `api-tokens/create` | api-tokens.create | `ApiTokenController@create` | api-tokens.create OR api-tokens.* |
| DELETE | `api-tokens/{tokenId}` | api-tokens.destroy | `ApiTokenController@destroy` | api-tokens.destroy OR api-tokens.* |

### `api/companies` (8 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `api/companies` | api.companies.index | `Api\CompanyController@index` | companies.index |
| POST | `api/companies/{company}/attendance/logs` | api.attendance-logs.store | `Api\AttendanceLogController@store` | attendance.attendance-logs.store |
| GET | `api/companies/{company}/attendance/logs` | api.attendance-logs.index | `Api\AttendanceLogController@index` | attendance.attendance-logs.index |
| POST | `api/companies/{company}/documents` | api.documents.store | `Api\DocumentController@store` | documents.store |
| GET | `api/companies/{company}/documents/{documentId}` | api.documents.show | `Api\DocumentController@show` | documents.show |
| POST | `api/companies/{company}/documents/{documentId}/files` | api.documents.files.store | `Api\DocumentController@attachFile` | documents.files.store |
| GET | `api/companies/{company}/employees` | api.employees.index | `Api\EmployeeController@index` | hr.employees.index |
| POST | `api/companies/{company}/employees` | api.employees.store | `Api\EmployeeController@store` | hr.employees.store |

### `api/devices` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `api/devices/pair` | api.devices.pair | `Api\DevicePairingController@pair` | NONE — public/pre-auth |
| GET | `api/devices/ping` | api.devices.ping | `Api\DevicePairingController@ping` | (device-credential gate, not a Spatie permission) |

### `api/sync` (3 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `api/sync/numbering/reserve-range` | api.sync.numbering.reserve-range | `Api\NumberingController@reserveRange` | (device-credential gate, not a Spatie permission) |
| GET | `api/sync/pull` | api.sync.pull | `Api\SyncController@pull` | (device-credential gate, not a Spatie permission) |
| POST | `api/sync/push` | api.sync.push | `Api\SyncController@push` | (device-credential gate, not a Spatie permission) |

### `attendance` (32 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `attendance/attendance-logs` | attendance.attendance-logs.index | `AttendanceLogController@index` | attendance.attendance-logs.index OR attendance.attendance-logs.* |
| POST | `attendance/attendance-logs` | attendance.attendance-logs.store | `AttendanceLogController@store` | attendance.attendance-logs.store OR attendance.attendance-logs.* |
| POST | `attendance/attendance-logs-bulk` | attendance.attendance-logs.bulk-store | `AttendanceLogController@bulkStore` | attendance.attendance-logs.bulk-store OR attendance.attendance-logs.* |
| GET | `attendance/attendance-logs-bulk/create` | attendance.attendance-logs.bulk-create | `AttendanceLogController@bulkCreate` | attendance.attendance-logs.bulk-create OR attendance.attendance-logs.* |
| GET | `attendance/attendance-logs-bulk/search-employee` | attendance.attendance-logs.search-employee | `AttendanceLogController@searchEmployee` | attendance.attendance-logs.search-employee OR attendance.attendance-logs.* |
| GET | `attendance/attendance-logs-import` | attendance.attendance-logs.import | `AttendanceLogController@importForm` | attendance.attendance-logs.import OR attendance.attendance-logs.* |
| POST | `attendance/attendance-logs-import` | attendance.attendance-logs.import.store | `AttendanceLogController@importStore` | attendance.attendance-logs.import.store OR attendance.attendance-logs.import.* |
| POST | `attendance/attendance-logs-import/preview` | attendance.attendance-logs.import.preview | `AttendanceLogController@importPreview` | attendance.attendance-logs.import.preview OR attendance.attendance-logs.import.* |
| GET | `attendance/attendance-logs/create` | attendance.attendance-logs.create | `AttendanceLogController@create` | attendance.attendance-logs.create OR attendance.attendance-logs.* |
| PUT|PATCH | `attendance/attendance-logs/{attendance_log}` | attendance.attendance-logs.update | `AttendanceLogController@update` | attendance.attendance-logs.update OR attendance.attendance-logs.* |
| DELETE | `attendance/attendance-logs/{attendance_log}` | attendance.attendance-logs.destroy | `AttendanceLogController@destroy` | attendance.attendance-logs.destroy OR attendance.attendance-logs.* |
| GET | `attendance/attendance-logs/{attendance_log}` | attendance.attendance-logs.show | `AttendanceLogController@show` | attendance.attendance-logs.show OR attendance.attendance-logs.* |
| GET | `attendance/attendance-logs/{attendance_log}/edit` | attendance.attendance-logs.edit | `AttendanceLogController@edit` | attendance.attendance-logs.edit OR attendance.attendance-logs.* |
| POST | `attendance/attendance-logs/{attendance_log}/recalculate` | attendance.attendance-logs.recalculate | `AttendanceLogController@recalculate` | attendance.attendance-logs.recalculate OR attendance.attendance-logs.* |
| GET | `attendance/monthly-attendances` | attendance.monthly-attendances.index | `MonthlyAttendanceController@index` | attendance.monthly-attendances.index OR attendance.monthly-attendances.* |
| POST | `attendance/monthly-attendances` | attendance.monthly-attendances.store | `MonthlyAttendanceController@store` | attendance.monthly-attendances.store OR attendance.monthly-attendances.* |
| POST | `attendance/monthly-attendances-bulk` | attendance.monthly-attendances.bulk-store | `MonthlyAttendanceController@bulkStore` | attendance.monthly-attendances.bulk-store OR attendance.monthly-attendances.* |
| GET | `attendance/monthly-attendances-bulk/create` | attendance.monthly-attendances.bulk-create | `MonthlyAttendanceController@bulkCreate` | attendance.monthly-attendances.bulk-create OR attendance.monthly-attendances.* |
| GET | `attendance/monthly-attendances/create` | attendance.monthly-attendances.create | `MonthlyAttendanceController@create` | attendance.monthly-attendances.create OR attendance.monthly-attendances.* |
| GET | `attendance/monthly-attendances/{monthly_attendance}` | attendance.monthly-attendances.show | `MonthlyAttendanceController@show` | attendance.monthly-attendances.show OR attendance.monthly-attendances.* |
| PUT|PATCH | `attendance/monthly-attendances/{monthly_attendance}` | attendance.monthly-attendances.update | `MonthlyAttendanceController@update` | attendance.monthly-attendances.update OR attendance.monthly-attendances.* |
| DELETE | `attendance/monthly-attendances/{monthly_attendance}` | attendance.monthly-attendances.destroy | `MonthlyAttendanceController@destroy` | attendance.monthly-attendances.destroy OR attendance.monthly-attendances.* |
| POST | `attendance/monthly-attendances/{monthly_attendance}/attendance-logs/recalculate` | attendance.attendance-logs.recalculate-all | `AttendanceLogController@recalculateAll` | attendance.attendance-logs.recalculate-all OR attendance.attendance-logs.* |
| GET | `attendance/monthly-attendances/{monthly_attendance}/edit` | attendance.monthly-attendances.edit | `MonthlyAttendanceController@edit` | attendance.monthly-attendances.edit OR attendance.monthly-attendances.* |
| POST | `attendance/monthly-attendances/{monthly_attendance}/payroll` | attendance.monthly-attendances.payroll.store | `PayrollController@store` | attendance.monthly-attendances.payroll.store OR attendance.monthly-attendances.payroll.* |
| POST | `attendance/monthly-attendances/{monthly_attendance}/recalculate` | attendance.monthly-attendances.recalculate | `MonthlyAttendanceController@recalculate` | attendance.monthly-attendances.recalculate OR attendance.monthly-attendances.* |
| GET | `attendance/work-shifts` | attendance.work-shifts.index | `WorkShiftController@index` | attendance.work-shifts.index OR attendance.work-shifts.* |
| POST | `attendance/work-shifts` | attendance.work-shifts.store | `WorkShiftController@store` | attendance.work-shifts.store OR attendance.work-shifts.* |
| GET | `attendance/work-shifts/create` | attendance.work-shifts.create | `WorkShiftController@create` | attendance.work-shifts.create OR attendance.work-shifts.* |
| PUT|PATCH | `attendance/work-shifts/{work_shift}` | attendance.work-shifts.update | `WorkShiftController@update` | attendance.work-shifts.update OR attendance.work-shifts.* |
| DELETE | `attendance/work-shifts/{work_shift}` | attendance.work-shifts.destroy | `WorkShiftController@destroy` | attendance.work-shifts.destroy OR attendance.work-shifts.* |
| GET | `attendance/work-shifts/{work_shift}/edit` | attendance.work-shifts.edit | `WorkShiftController@edit` | attendance.work-shifts.edit OR attendance.work-shifts.* |

### `backups` (5 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `backups/create` | backups.create | `BackupController@create` | backups.create OR backups.* |
| GET | `backups/document-files-size` | backups.document-files-size | `BackupController@documentFilesSize` | backups.document-files-size OR backups.* |
| POST | `backups/export` | backups.export | `BackupController@export` | backups.export OR backups.* |
| POST | `backups/import` | backups.import | `BackupController@import` | backups.import OR backups.* |
| GET | `backups/upload` | backups.upload | `BackupController@upload` | backups.upload OR backups.* |

### `bank-accounts` (8 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `bank-accounts` | bank-accounts.index | `BankAccountController@index` | bank-accounts.index OR bank-accounts.* |
| POST | `bank-accounts` | bank-accounts.store | `BankAccountController@store` | bank-accounts.store OR bank-accounts.* |
| GET | `bank-accounts/create` | bank-accounts.create | `BankAccountController@create` | bank-accounts.create OR bank-accounts.* |
| GET | `bank-accounts/search-bank` | bank-accounts.search-bank | `BankAccountController@searchBank` | bank-accounts.search-bank OR bank-accounts.* |
| GET | `bank-accounts/{bank_account}` | bank-accounts.show | `BankAccountController@show` | bank-accounts.show OR bank-accounts.* |
| PUT|PATCH | `bank-accounts/{bank_account}` | bank-accounts.update | `BankAccountController@update` | bank-accounts.update OR bank-accounts.* |
| DELETE | `bank-accounts/{bank_account}` | bank-accounts.destroy | `BankAccountController@destroy` | bank-accounts.destroy OR bank-accounts.* |
| GET | `bank-accounts/{bank_account}/edit` | bank-accounts.edit | `BankAccountController@edit` | bank-accounts.edit OR bank-accounts.* |

### `bank-reconciliation` (4 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `bank-reconciliation/lines/{line}/match` | bank-reconciliation.match | `BankReconciliationController@match` | bank-reconciliation.match OR bank-reconciliation.* |
| POST | `bank-reconciliation/lines/{line}/unmatch` | bank-reconciliation.unmatch | `BankReconciliationController@unmatch` | bank-reconciliation.unmatch OR bank-reconciliation.* |
| GET | `bank-reconciliation/{bankAccount}` | bank-reconciliation.index | `BankReconciliationController@index` | bank-reconciliation.index OR bank-reconciliation.* |
| POST | `bank-reconciliation/{bankAccount}/import` | bank-reconciliation.import | `BankReconciliationController@import` | bank-reconciliation.import OR bank-reconciliation.* |

### `banks` (7 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `banks` | banks.index | `BankController@index` | banks.index OR banks.* |
| POST | `banks` | banks.store | `BankController@store` | banks.store OR banks.* |
| GET | `banks/create` | banks.create | `BankController@create` | banks.create OR banks.* |
| GET | `banks/{bank}` | banks.show | `BankController@show` | banks.show OR banks.* |
| PUT|PATCH | `banks/{bank}` | banks.update | `BankController@update` | banks.update OR banks.* |
| DELETE | `banks/{bank}` | banks.destroy | `BankController@destroy` | banks.destroy OR banks.* |
| GET | `banks/{bank}/edit` | banks.edit | `BankController@edit` | banks.edit OR banks.* |

### `change-company` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `change-company/{company}` | change-company | `CompanyController@setActiveCompany` | change-company OR change-company.* |

### `chequebooks` (7 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `chequebooks` | chequebooks.index | `ChequebookController@index` | chequebooks.index OR chequebooks.* |
| POST | `chequebooks` | chequebooks.store | `ChequebookController@store` | chequebooks.store OR chequebooks.* |
| GET | `chequebooks/create` | chequebooks.create | `ChequebookController@create` | chequebooks.create OR chequebooks.* |
| GET | `chequebooks/{chequebook}` | chequebooks.show | `ChequebookController@show` | chequebooks.show OR chequebooks.* |
| PUT|PATCH | `chequebooks/{chequebook}` | chequebooks.update | `ChequebookController@update` | chequebooks.update OR chequebooks.* |
| DELETE | `chequebooks/{chequebook}` | chequebooks.destroy | `ChequebookController@destroy` | chequebooks.destroy OR chequebooks.* |
| GET | `chequebooks/{chequebook}/edit` | chequebooks.edit | `ChequebookController@edit` | chequebooks.edit OR chequebooks.* |

### `cheques` (9 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `cheques` | cheques.index | `ChequeController@index` | cheques.index OR cheques.* |
| POST | `cheques` | cheques.store | `ChequeController@store` | cheques.store OR cheques.* |
| GET | `cheques/create` | cheques.create | `ChequeController@create` | cheques.create OR cheques.* |
| GET | `cheques/report` | cheques.report | `ChequeController@report` | cheques.report OR cheques.* |
| GET | `cheques/{cheque}` | cheques.show | `ChequeController@show` | cheques.show OR cheques.* |
| PUT|PATCH | `cheques/{cheque}` | cheques.update | `ChequeController@update` | cheques.update OR cheques.* |
| DELETE | `cheques/{cheque}` | cheques.destroy | `ChequeController@destroy` | cheques.destroy OR cheques.* |
| GET | `cheques/{cheque}/edit` | cheques.edit | `ChequeController@edit` | cheques.edit OR cheques.* |
| POST | `cheques/{cheque}/transition/{action}` | cheques.transition | `ChequeController@transition` | cheques.transition OR cheques.* |

### `crm` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `crm/dashboard` | crm.dashboard | `CrmDashboardController@index` | crm.dashboard OR crm.* |

### `customer-groups` (7 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `customer-groups` | customer-groups.index | `CustomerGroupController@index` | customer-groups.index OR customer-groups.* |
| POST | `customer-groups` | customer-groups.store | `CustomerGroupController@store` | customer-groups.store OR customer-groups.* |
| GET | `customer-groups/create` | customer-groups.create | `CustomerGroupController@create` | customer-groups.create OR customer-groups.* |
| GET | `customer-groups/{customer_group}` | customer-groups.show | `CustomerGroupController@show` | customer-groups.show OR customer-groups.* |
| PUT|PATCH | `customer-groups/{customer_group}` | customer-groups.update | `CustomerGroupController@update` | customer-groups.update OR customer-groups.* |
| DELETE | `customer-groups/{customer_group}` | customer-groups.destroy | `CustomerGroupController@destroy` | customer-groups.destroy OR customer-groups.* |
| GET | `customer-groups/{customer_group}/edit` | customer-groups.edit | `CustomerGroupController@edit` | customer-groups.edit OR customer-groups.* |

### `customers` (17 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `customers` | customers.index | `CustomerController@index` | customers.index OR customers.* |
| POST | `customers` | customers.store | `CustomerController@store` | customers.store OR customers.* |
| GET | `customers/create` | customers.create | `CustomerController@create` | customers.create OR customers.* |
| GET | `customers/export` | customers.export | `CustomerController@export` | customers.export OR customers.* |
| GET | `customers/import` | customers.import | `CustomerController@importForm` | customers.import OR customers.* |
| POST | `customers/import` | customers.import.store | `CustomerController@import` | customers.import.store OR customers.import.* |
| GET | `customers/{customer}` | customers.show | `CustomerController@show` | customers.show OR customers.* |
| PUT|PATCH | `customers/{customer}` | customers.update | `CustomerController@update` | customers.update OR customers.* |
| DELETE | `customers/{customer}` | customers.destroy | `CustomerController@destroy` | customers.destroy OR customers.* |
| GET | `customers/{customer}/comments` | comments.index | `CommentController@index` | comments.index OR comments.* |
| POST | `customers/{customer}/comments` | comments.store | `CommentController@store` | comments.store OR comments.* |
| GET | `customers/{customer}/comments/create` | comments.create | `CommentController@create` | comments.create OR comments.* |
| PUT | `customers/{customer}/comments/{comment}` | comments.update | `CommentController@update` | comments.update OR comments.* |
| DELETE | `customers/{customer}/comments/{comment}` | comments.destroy | `CommentController@destroy` | comments.destroy OR comments.* |
| GET | `customers/{customer}/comments/{comment}/edit` | comments.edit | `CommentController@edit` | comments.edit OR comments.* |
| POST | `customers/{customer}/credit-payments` | customers.credit-payments.store | `PaymentAllocationController@storeCreditPayment` | customers.credit-payments.store OR customers.credit-payments.* |
| GET | `customers/{customer}/edit` | customers.edit | `CustomerController@edit` | customers.edit OR customers.* |

### `devices` (3 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `devices` | devices.index | `DeviceController@index` | devices.index OR devices.* |
| POST | `devices/activation-codes` | devices.activation-codes.store | `DeviceController@storeActivationCode` | devices.activation-codes.store OR devices.activation-codes.* |
| POST | `devices/{installation}/revoke` | devices.revoke | `DeviceController@revoke` | devices.revoke OR devices.* |

### `documents` (27 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `documents` | documents.index | `DocumentController@index` | documents.index OR documents.* |
| POST | `documents` | documents.store | `DocumentController@store` | documents.store OR documents.* |
| POST | `documents/approve-all` | documents.approve-all | `DocumentController@approveAll` | documents.approve-all OR documents.* |
| GET | `documents/create` | documents.create | `DocumentController@create` | documents.create OR documents.* |
| GET | `documents/export` | documents.export | `DocumentController@exportForm` | documents.export OR documents.* |
| POST | `documents/export` | documents.export.download | `DocumentController@export` | documents.export.download OR documents.export.* |
| GET | `documents/import` | documents.import | `DocumentController@importForm` | documents.import OR documents.* |
| POST | `documents/import` | documents.import.store | `DocumentController@import` | documents.import.store OR documents.import.* |
| GET | `documents/search-account-balance` | documents.search-account-balance | `DocumentController@searchAccountBalance` | documents.search-account-balance OR documents.* |
| GET | `documents/sort-numbers` | documents.sort-numbers | `DocumentController@sortNumbers` | documents.sort-numbers OR documents.* |
| POST | `documents/sort-numbers/process` | documents.sort-numbers.process | `DocumentController@processSorting` | documents.sort-numbers.process OR documents.sort-numbers.* |
| POST | `documents/sort-numbers/start` | documents.sort-numbers.start | `DocumentController@startSorting` | documents.sort-numbers.start OR documents.sort-numbers.* |
| GET | `documents/{document}` | documents.show | `DocumentController@show` | documents.show OR documents.* |
| PUT|PATCH | `documents/{document}` | documents.update | `DocumentController@update` | documents.update OR documents.* |
| DELETE | `documents/{document}` | documents.destroy | `DocumentController@destroy` | documents.destroy OR documents.* |
| POST | `documents/{document}/change-status` | documents.change-status | `DocumentController@changeStatus` | documents.change-status OR documents.* |
| GET | `documents/{document}/duplicate` | documents.duplicate | `DocumentController@duplicate` | documents.duplicate OR documents.* |
| GET | `documents/{document}/edit` | documents.edit | `DocumentController@edit` | documents.edit OR documents.* |
| GET | `documents/{document}/files/create` | documents.files.create | `DocumentFileController@create` | documents.files.create OR documents.files.* |
| POST | `documents/{document}/files/store` | documents.files.store | `DocumentFileController@store` | documents.files.store OR documents.files.* |
| PUT | `documents/{document}/files/{documentFile}` | documents.files.update | `DocumentFileController@update` | documents.files.update OR documents.files.* |
| DELETE | `documents/{document}/files/{documentFile}` | documents.files.destroy | `DocumentFileController@destroy` | documents.files.destroy OR documents.files.* |
| GET | `documents/{document}/files/{documentFile}/download` | documents.files.download | `DocumentFileController@download` | documents.files.download OR documents.files.* |
| GET | `documents/{document}/files/{documentFile}/edit` | documents.files.edit | `DocumentFileController@edit` | documents.files.edit OR documents.files.* |
| GET | `documents/{document}/files/{documentFile}/view` | documents.files.view | `DocumentFileController@view` | documents.files.view OR documents.files.* |
| GET | `documents/{document}/print` | documents.print | `DocumentController@print` | documents.print OR documents.* |
| POST | `documents/{document}/transfer` | documents.transfer | `DocumentController@transfer` | documents.transfer OR documents.* |

### `employee-portal` (15 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `employee-portal` | employee-portal.dashboard | `EmployeePortalController@dashboard` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/attendance-logs` | employee-portal.attendance-logs | `EmployeePortalController@attendanceLogs` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/change-employee-information` | employee-portal.change-employee-information | `EmployeePortalController@changeEmployeeInformation` | (employee-self-service gate, not a Spatie permission) |
| PUT | `employee-portal/change-employee-information` | employee-portal.update-employee-information | `EmployeePortalController@updateEmployeeInformation` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/employee` | employee-portal.employee.show | `EmployeePortalController@employeeShow` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/monthly-attendances` | employee-portal.monthly-attendances | `EmployeePortalController@monthlyAttendances` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/monthly-attendances/{monthly_attendance}` | employee-portal.monthly-attendances.show | `EmployeePortalController@monthlyAttendanceShow` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/payrolls` | employee-portal.payrolls | `EmployeePortalController@payrolls` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/payrolls/{payroll}` | employee-portal.payrolls.show | `EmployeePortalController@payrollShow` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/personnel-requests` | employee-portal.personnel-requests.index | `EmployeePortalController@personnelRequests` | (employee-self-service gate, not a Spatie permission) |
| POST | `employee-portal/personnel-requests` | employee-portal.personnel-requests.store | `EmployeePortalController@storePersonnelRequest` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/personnel-requests/create` | employee-portal.personnel-requests.create | `EmployeePortalController@createPersonnelRequest` | (employee-self-service gate, not a Spatie permission) |
| PUT | `employee-portal/personnel-requests/{personnel_request}` | employee-portal.personnel-requests.update | `EmployeePortalController@updatePersonnelRequest` | (employee-self-service gate, not a Spatie permission) |
| DELETE | `employee-portal/personnel-requests/{personnel_request}` | employee-portal.personnel-requests.destroy | `EmployeePortalController@destroyPersonnelRequest` | (employee-self-service gate, not a Spatie permission) |
| GET | `employee-portal/personnel-requests/{personnel_request}/edit` | employee-portal.personnel-requests.edit | `EmployeePortalController@editPersonnelRequest` | (employee-self-service gate, not a Spatie permission) |

### `forgot-password` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `forgot-password` | password.request | `Auth\PasswordResetController@showForgotPasswordForm` | NONE — public/pre-auth |
| POST | `forgot-password` | password.email | `Auth\PasswordResetController@sendResetLink` | NONE — public/pre-auth |

### `home` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `home/summary/{metric}` | home.summary | `HomeController@summaryMetric` | home.summary OR home.* |

### `hr` (31 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `hr/employees` | hr.employees.index | `EmployeeController@index` | hr.employees.index OR hr.employees.* |
| POST | `hr/employees` | hr.employees.store | `EmployeeController@store` | hr.employees.store OR hr.employees.* |
| GET | `hr/employees/create` | hr.employees.create | `EmployeeController@create` | hr.employees.create OR hr.employees.* |
| GET | `hr/employees/export` | hr.employees.export | `EmployeeController@export` | hr.employees.export OR hr.employees.* |
| GET | `hr/employees/{employee}` | hr.employees.show | `EmployeeController@show` | hr.employees.show OR hr.employees.* |
| PUT|PATCH | `hr/employees/{employee}` | hr.employees.update | `EmployeeController@update` | hr.employees.update OR hr.employees.* |
| DELETE | `hr/employees/{employee}` | hr.employees.destroy | `EmployeeController@destroy` | hr.employees.destroy OR hr.employees.* |
| GET | `hr/employees/{employee}/edit` | hr.employees.edit | `EmployeeController@edit` | hr.employees.edit OR hr.employees.* |
| GET | `hr/org-charts` | hr.org-charts.index | `OrgChartController@index` | hr.org-charts.index OR hr.org-charts.* |
| POST | `hr/org-charts` | hr.org-charts.store | `OrgChartController@store` | hr.org-charts.store OR hr.org-charts.* |
| GET | `hr/org-charts/create` | hr.org-charts.create | `OrgChartController@create` | hr.org-charts.create OR hr.org-charts.* |
| GET | `hr/org-charts/{org_chart}` | hr.org-charts.show | `OrgChartController@show` | hr.org-charts.show OR hr.org-charts.* |
| PUT|PATCH | `hr/org-charts/{org_chart}` | hr.org-charts.update | `OrgChartController@update` | hr.org-charts.update OR hr.org-charts.* |
| DELETE | `hr/org-charts/{org_chart}` | hr.org-charts.destroy | `OrgChartController@destroy` | hr.org-charts.destroy OR hr.org-charts.* |
| GET | `hr/org-charts/{org_chart}/edit` | hr.org-charts.edit | `OrgChartController@edit` | hr.org-charts.edit OR hr.org-charts.* |
| GET | `hr/organization-units` | hr.organization-units.index | `OrganizationUnitController@index` | hr.organization-units.index OR hr.organization-units.* |
| POST | `hr/organization-units` | hr.organization-units.store | `OrganizationUnitController@store` | hr.organization-units.store OR hr.organization-units.* |
| GET | `hr/organization-units/create` | hr.organization-units.create | `OrganizationUnitController@create` | hr.organization-units.create OR hr.organization-units.* |
| GET | `hr/organization-units/{organization_unit}` | hr.organization-units.show | `OrganizationUnitController@show` | hr.organization-units.show OR hr.organization-units.* |
| PUT|PATCH | `hr/organization-units/{organization_unit}` | hr.organization-units.update | `OrganizationUnitController@update` | hr.organization-units.update OR hr.organization-units.* |
| DELETE | `hr/organization-units/{organization_unit}` | hr.organization-units.destroy | `OrganizationUnitController@destroy` | hr.organization-units.destroy OR hr.organization-units.* |
| GET | `hr/organization-units/{organization_unit}/edit` | hr.organization-units.edit | `OrganizationUnitController@edit` | hr.organization-units.edit OR hr.organization-units.* |
| GET | `hr/personnel-requests` | hr.personnel-requests.index | `PersonnelRequestController@index` | hr.personnel-requests.index OR hr.personnel-requests.* |
| POST | `hr/personnel-requests` | hr.personnel-requests.store | `PersonnelRequestController@store` | hr.personnel-requests.store OR hr.personnel-requests.* |
| GET | `hr/personnel-requests/create` | hr.personnel-requests.create | `PersonnelRequestController@create` | hr.personnel-requests.create OR hr.personnel-requests.* |
| GET | `hr/personnel-requests/{personnel_request}` | hr.personnel-requests.show | `PersonnelRequestController@show` | hr.personnel-requests.show OR hr.personnel-requests.* |
| PUT|PATCH | `hr/personnel-requests/{personnel_request}` | hr.personnel-requests.update | `PersonnelRequestController@update` | hr.personnel-requests.update OR hr.personnel-requests.* |
| DELETE | `hr/personnel-requests/{personnel_request}` | hr.personnel-requests.destroy | `PersonnelRequestController@destroy` | hr.personnel-requests.destroy OR hr.personnel-requests.* |
| PATCH | `hr/personnel-requests/{personnel_request}/approve` | hr.personnel-requests.approve | `PersonnelRequestController@approve` | hr.personnel-requests.approve OR hr.personnel-requests.* |
| GET | `hr/personnel-requests/{personnel_request}/edit` | hr.personnel-requests.edit | `PersonnelRequestController@edit` | hr.personnel-requests.edit OR hr.personnel-requests.* |
| PATCH | `hr/personnel-requests/{personnel_request}/reject` | hr.personnel-requests.reject | `PersonnelRequestController@reject` | hr.personnel-requests.reject OR hr.personnel-requests.* |

### `impersonation` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `impersonation/leave` | impersonation.leave | `Management\UserController@leaveImpersonation` | NONE — auth only |

### `invoices` (43 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `invoices` | invoices.index | `InvoiceController@index` | invoices.index OR invoices.* |
| POST | `invoices` | invoices.store | `InvoiceController@store` | invoices.store OR invoices.* |
| GET | `invoices/ancillary-costs` | ancillary-costs.index | `AncillaryCostController@index` | ancillary-costs.index OR ancillary-costs.* |
| GET | `invoices/ancillary-costs/get-products/{invoice_id}` | ancillary-costs.get-products | `AncillaryCostController@getBuyInvoiceProducts` | ancillary-costs.get-products OR ancillary-costs.* |
| GET | `invoices/ancillary-costs/search-customer` | ancillary-costs.search-customer | `AncillaryCostController@searchCustomer` | ancillary-costs.search-customer OR ancillary-costs.* |
| GET | `invoices/ancillary-costs/search-invoice` | ancillary-costs.search-invoice | `AncillaryCostController@searchInvoice` | ancillary-costs.search-invoice OR ancillary-costs.* |
| POST | `invoices/ancillary-costs/{ancillary_cost}/change-status/{status}` | ancillary-costs.change-status | `AncillaryCostController@changeStatus` | ancillary-costs.change-status OR ancillary-costs.* |
| GET | `invoices/create` | invoices.create | `InvoiceController@create` | invoices.create OR invoices.* |
| GET | `invoices/dashboard` | invoices.dashboard | `InvoiceDashboardController@index` | invoices.dashboard OR invoices.* |
| GET | `invoices/export` | invoices.export | `InvoiceController@export` | invoices.export OR invoices.* |
| GET | `invoices/get-items/{invoice}` | invoices.get-items | `InvoiceController@getItems` | invoices.get-items OR invoices.* |
| GET | `invoices/inactive` | invoices.inactive | `InvoiceController@inactiveInvoices` | invoices.inactive OR invoices.* |
| POST | `invoices/inactive/approve` | invoices.inactive.approve | `InvoiceController@approveInactiveInvoices` | invoices.inactive.approve OR invoices.inactive.* |
| GET | `invoices/moadian-histories` | invoices.moadian-histories.index | `MoadianHistoryController@index` | invoices.moadian-histories.index OR invoices.moadian-histories.* |
| GET | `invoices/search-customer` | invoices.search-customer | `InvoiceController@searchCustomer` | invoices.search-customer OR invoices.* |
| GET | `invoices/search-product-service` | invoices.search-product-service | `InvoiceController@searchProductService` | invoices.search-product-service OR invoices.* |
| GET | `invoices/search/{invoice_type}` | invoices.search | `InvoiceController@search` | invoices.search OR invoices.* |
| GET | `invoices/{invoice}` | invoices.show | `InvoiceController@show` | invoices.show OR invoices.* |
| PUT|PATCH | `invoices/{invoice}` | invoices.update | `InvoiceController@update` | invoices.update OR invoices.* |
| DELETE | `invoices/{invoice}` | invoices.destroy | `InvoiceController@destroy` | invoices.destroy OR invoices.* |
| POST | `invoices/{invoice}/ancillary-costs` | invoices.ancillary-costs.store | `AncillaryCostController@store` | invoices.ancillary-costs.store OR invoices.ancillary-costs.* |
| GET | `invoices/{invoice}/ancillary-costs/create` | invoices.ancillary-costs.create | `AncillaryCostController@create` | invoices.ancillary-costs.create OR invoices.ancillary-costs.* |
| GET | `invoices/{invoice}/ancillary-costs/{ancillary_cost}` | invoices.ancillary-costs.show | `AncillaryCostController@show` | invoices.ancillary-costs.show OR invoices.ancillary-costs.* |
| PUT | `invoices/{invoice}/ancillary-costs/{ancillary_cost}` | invoices.ancillary-costs.update | `AncillaryCostController@update` | invoices.ancillary-costs.update OR invoices.ancillary-costs.* |
| DELETE | `invoices/{invoice}/ancillary-costs/{ancillary_cost}` | invoices.ancillary-costs.destroy | `AncillaryCostController@destroy` | invoices.ancillary-costs.destroy OR invoices.ancillary-costs.* |
| GET | `invoices/{invoice}/ancillary-costs/{ancillary_cost}/edit` | invoices.ancillary-costs.edit | `AncillaryCostController@edit` | invoices.ancillary-costs.edit OR invoices.ancillary-costs.* |
| POST | `invoices/{invoice}/change-status/{status}` | invoices.change-status | `InvoiceController@changeStatus` | invoices.change-status OR invoices.* |
| GET | `invoices/{invoice}/conflicts` | invoices.conflicts | `InvoiceController@conflicts` | invoices.conflicts OR invoices.* |
| GET | `invoices/{invoice}/conflicts/{type}` | invoices.conflicts.more | `InvoiceController@showMoreConflictsByType` | invoices.conflicts.more OR invoices.conflicts.* |
| GET | `invoices/{invoice}/edit` | invoices.edit | `InvoiceController@edit` | invoices.edit OR invoices.* |
| POST | `invoices/{invoice}/group-action` | invoices.group-action | `InvoiceController@groupAction` | invoices.group-action OR invoices.* |
| POST | `invoices/{invoice}/moadian-check-status` | invoices.moadian-check-status | `MoadianHistoryController@checkStatus` | invoices.moadian-check-status OR invoices.* |
| GET | `invoices/{invoice}/moadian-histories` | invoices.moadian-histories.show | `MoadianHistoryController@show` | invoices.moadian-histories.show OR invoices.moadian-histories.* |
| POST | `invoices/{invoice}/payments` | invoices.payments.store | `PaymentController@store` | invoices.payments.store OR invoices.payments.* |
| POST | `invoices/{invoice}/payments/cheque` | invoices.payments.store-cheque | `PaymentController@storeCheque` | invoices.payments.store-cheque OR invoices.payments.* |
| DELETE | `invoices/{invoice}/payments/{payment}` | invoices.payments.destroy | `PaymentController@destroy` | invoices.payments.destroy OR invoices.payments.* |
| POST | `invoices/{invoice}/payments/{payment}/document` | invoices.payments.create-document | `PaymentController@createDocument` | invoices.payments.create-document OR invoices.payments.* |
| DELETE | `invoices/{invoice}/payments/{payment}/document` | invoices.payments.destroy-document | `PaymentController@destroyDocument` | invoices.payments.destroy-document OR invoices.payments.* |
| GET | `invoices/{invoice}/print` | invoices.print | `InvoiceController@print` | invoices.print OR invoices.* |
| POST | `invoices/{invoice}/send-moadian` | invoices.send-moadian | `InvoiceController@sendMoadian` | invoices.send-moadian OR invoices.* |
| POST | `invoices/{invoice}/transfer` | invoices.transfer | `InvoiceController@transfer` | invoices.transfer OR invoices.* |
| GET | `invoices/{invoice}/void` | invoices.void-form | `InvoiceController@showVoidForm` | invoices.void-form OR invoices.* |
| POST | `invoices/{invoice}/void` | invoices.void | `InvoiceController@voidInvoice` | invoices.void OR invoices.* |

### `locale` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `locale` | locale | `Auth\LoginController@locale` | NONE — public/pre-auth |

### `login` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `login` | login | `Auth\LoginController@showLoginForm` | NONE — public/pre-auth |
| POST | `login` |  | `Auth\LoginController@login` | NONE — public/pre-auth |

### `logout` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `logout` | logout | `Auth\LoginController@logout` | NONE — public/pre-auth |

### `management` (45 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `management` | management.dashboard | `HomeController@managementDashboard` | access-super-admin-panel |
| GET | `management/activity-logs` | management.activity-logs.index | `Management\ActivityLogController@index` | access-super-admin-panel |
| GET | `management/activity-logs/{activity}/details` | management.activity-logs.details | `Management\ActivityLogController@details` | access-super-admin-panel |
| GET | `management/companies` | companies.index | `CompanyController@index` | companies.index OR companies.* |
| POST | `management/companies` | companies.store | `CompanyController@store` | companies.store OR companies.* |
| POST | `management/companies/close-fiscal-year/{company}` | companies.close-fiscal-year | `CompanyController@closeFiscalYear` | companies.close-fiscal-year OR companies.* |
| GET | `management/companies/create` | companies.create | `CompanyController@create` | companies.create OR companies.* |
| GET | `management/companies/{company}` | companies.show | `CompanyController@show` | companies.show OR companies.* |
| PUT|PATCH | `management/companies/{company}` | companies.update | `CompanyController@update` | companies.update OR companies.* |
| DELETE | `management/companies/{company}` | companies.destroy | `CompanyController@destroy` | companies.destroy OR companies.* |
| GET | `management/companies/{company}/closing-wizard` | companies.closing-wizard | `CompanyController@closingWizard` | companies.closing-wizard OR companies.* |
| POST | `management/companies/{company}/closing-wizard/step1` | companies.closing-wizard.step1 | `CompanyController@closingWizardStep1` | companies.closing-wizard.step1 OR companies.closing-wizard.* |
| POST | `management/companies/{company}/closing-wizard/step3` | companies.closing-wizard.step3 | `CompanyController@closingWizardStep3` | companies.closing-wizard.step3 OR companies.closing-wizard.* |
| GET | `management/companies/{company}/edit` | companies.edit | `CompanyController@edit` | companies.edit OR companies.* |
| GET | `management/configs` | configs.index | `ConfigController@index` | configs.index OR configs.* |
| POST | `management/configs` | configs.store | `ConfigController@store` | configs.store OR configs.* |
| GET | `management/configs/create` | configs.create | `ConfigController@create` | configs.create OR configs.* |
| GET | `management/configs/{config}` | configs.show | `ConfigController@show` | configs.show OR configs.* |
| PUT|PATCH | `management/configs/{config}` | configs.update | `ConfigController@update` | configs.update OR configs.* |
| DELETE | `management/configs/{config}` | configs.destroy | `ConfigController@destroy` | configs.destroy OR configs.* |
| GET | `management/configs/{config}/edit` | configs.edit | `ConfigController@edit` | configs.edit OR configs.* |
| GET | `management/permissions` | permissions.index | `Management\PermissionController@index` | access-super-admin-panel |
| POST | `management/permissions` | permissions.store | `Management\PermissionController@store` | access-super-admin-panel |
| GET | `management/permissions/create` | permissions.create | `Management\PermissionController@create` | access-super-admin-panel |
| PUT|PATCH | `management/permissions/{permission}` | permissions.update | `Management\PermissionController@update` | access-super-admin-panel |
| DELETE | `management/permissions/{permission}` | permissions.destroy | `Management\PermissionController@destroy` | access-super-admin-panel |
| GET | `management/permissions/{permission}/edit` | permissions.edit | `Management\PermissionController@edit` | access-super-admin-panel |
| GET | `management/roles` | roles.index | `Management\RoleController@index` | access-super-admin-panel |
| POST | `management/roles` | roles.store | `Management\RoleController@store` | access-super-admin-panel |
| GET | `management/roles/create` | roles.create | `Management\RoleController@create` | access-super-admin-panel |
| PUT|PATCH | `management/roles/{role}` | roles.update | `Management\RoleController@update` | access-super-admin-panel |
| DELETE | `management/roles/{role}` | roles.destroy | `Management\RoleController@destroy` | access-super-admin-panel |
| GET | `management/roles/{role}/edit` | roles.edit | `Management\RoleController@edit` | access-super-admin-panel |
| GET | `management/settings` | management.settings | `AboutController@index` | access-super-admin-panel |
| PUT | `management/settings` | update-global-configs | `AboutController@updateGlobalConfigs` | access-super-admin-panel |
| GET | `management/users` | users.index | `Management\UserController@index` | users.index OR users.* |
| POST | `management/users` | users.store | `Management\UserController@store` | users.store OR users.* |
| GET | `management/users/create` | users.create | `Management\UserController@create` | users.create OR users.* |
| GET | `management/users/{user}` | users.show | `Management\UserController@show` | access-super-admin-panel |
| PUT|PATCH | `management/users/{user}` | users.update | `Management\UserController@update` | users.update OR users.* |
| DELETE | `management/users/{user}` | users.destroy | `Management\UserController@destroy` | users.destroy OR users.* |
| POST | `management/users/{user}/create-employee` | users.create-employee | `Management\UserController@createEmployee` | users.create-employee OR users.* |
| GET | `management/users/{user}/edit` | users.edit | `Management\UserController@edit` | users.edit OR users.* |
| POST | `management/users/{user}/impersonate` | users.impersonate | `Management\UserController@impersonate` | users.impersonate OR users.* |
| POST | `management/users/{user}/verify` | users.verify | `Management\UserController@verify` | access-super-admin-panel |

### `monthly-budgets` (5 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `monthly-budgets` | budgets.index | `MonthlyBudgetController@index` | budgets.index OR budgets.* |
| PUT | `monthly-budgets` | budgets.store | `MonthlyBudgetController@store` | budgets.store OR budgets.* |
| POST | `monthly-budgets/rollover` | budgets.rollover | `MonthlyBudgetController@rollover` | budgets.rollover OR budgets.* |
| GET | `monthly-budgets/subjects/search/{month}/{scope?}` | budgets.search-subjects | `MonthlyBudgetController@searchSubjects` | budgets.search-subjects OR budgets.* |
| DELETE | `monthly-budgets/{monthlyBudget}` | budgets.destroy | `MonthlyBudgetController@destroy` | budgets.destroy OR budgets.* |

### `payments` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `payments/{payment}/allocations` | payments.allocations.store | `PaymentAllocationController@allocate` | payments.allocations.store OR payments.allocations.* |
| DELETE | `payments/{payment}/allocations/{allocation}` | payments.allocations.destroy | `PaymentAllocationController@destroy` | payments.allocations.destroy OR payments.allocations.* |

### `product-groups` (7 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `product-groups` | product-groups.index | `ProductGroupController@index` | product-groups.index OR product-groups.* |
| POST | `product-groups` | product-groups.store | `ProductGroupController@store` | product-groups.store OR product-groups.* |
| GET | `product-groups/create` | product-groups.create | `ProductGroupController@create` | product-groups.create OR product-groups.* |
| GET | `product-groups/{product_group}` | product-groups.show | `ProductGroupController@show` | product-groups.show OR product-groups.* |
| PUT|PATCH | `product-groups/{product_group}` | product-groups.update | `ProductGroupController@update` | product-groups.update OR product-groups.* |
| DELETE | `product-groups/{product_group}` | product-groups.destroy | `ProductGroupController@destroy` | product-groups.destroy OR product-groups.* |
| GET | `product-groups/{product_group}/edit` | product-groups.edit | `ProductGroupController@edit` | product-groups.edit OR product-groups.* |

### `products` (13 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `products` | products.index | `ProductController@index` | products.index OR products.* |
| POST | `products` | products.store | `ProductController@store` | products.store OR products.* |
| GET | `products/create` | products.create | `ProductController@create` | products.create OR products.* |
| GET | `products/export` | products.export | `ProductController@export` | products.export OR products.* |
| GET | `products/import` | products.import | `ProductController@importForm` | products.import OR products.* |
| POST | `products/import` | products.import.store | `ProductController@import` | products.import.store OR products.import.* |
| GET | `products/report` | products.report | `ProductController@report` | products.report OR products.* |
| GET | `products/search-product-group` | products.search-product-group | `ProductController@searchProductGroup` | products.search-product-group OR products.* |
| GET | `products/{product}` | products.show | `ProductController@show` | products.show OR products.* |
| PUT|PATCH | `products/{product}` | products.update | `ProductController@update` | products.update OR products.* |
| DELETE | `products/{product}` | products.destroy | `ProductController@destroy` | products.destroy OR products.* |
| GET | `products/{product}/edit` | products.edit | `ProductController@edit` | products.edit OR products.* |
| POST | `products/{product}/recalculate-quantity` | products.recalculate-quantity | `ProductController@recalculateQuantity` | products.recalculate-quantity OR products.* |

### `register` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `register` | register | `Auth\RegisterController@showRegisterForm` | NONE — public/pre-auth |
| POST | `register/email` | register.email | `Auth\RegisterController@registerWithEmail` | NONE — public/pre-auth |

### `registered-user` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `registered-user/company` | registered-user.company.create | `CompanyController@createCompanyForRegisteredUser` | NONE — auth only |
| POST | `registered-user/company` | registered-user.company.store | `CompanyController@storeCompanyForRegisteredUser` | NONE — auth only |

### `report` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `report` | report | `ReportEmailController@store` | report OR report.* |

### `reports` (15 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `reports/company-overview` | reports.company-overview | `ReportsController@companyOverview` | reports.company-overview OR reports.* |
| GET | `reports/company-overview/bank-account` | reports.company-overview.bank-account | `ReportsController@bankAccount` | reports.company-overview.bank-account OR reports.company-overview.* |
| GET | `reports/company-overview/cash-banks` | reports.company-overview.cash-banks | `ReportsController@cashAndBanksBalances` | reports.company-overview.cash-banks OR reports.company-overview.* |
| POST | `reports/company-overview/refresh-database` | reports.company-overview.refresh-database | `ReportsController@refreshDatabase` | reports.company-overview.refresh-database OR reports.company-overview.* |
| POST | `reports/company-overview/seed-demo-data` | reports.company-overview.seed-demo-data | `ReportsController@seedDemoData` | reports.company-overview.seed-demo-data OR reports.company-overview.* |
| GET | `reports/cost-income` | reports.cost-income | `CostIncomeController@index` | reports.cost-income OR reports.* |
| GET | `reports/documents` | reports.documents | `ReportsController@documents` | reports.documents OR reports.* |
| POST | `reports/documents/export` | reports.documents.export | `DocumentController@export` | reports.documents.export OR reports.documents.* |
| GET | `reports/journal` | reports.journal | `ReportsController@journal` | reports.journal OR reports.* |
| GET | `reports/ledger` | reports.ledger | `ReportsController@ledger` | reports.ledger OR reports.* |
| GET | `reports/result` | reports.result | `ReportsController@result` | reports.result OR reports.* |
| GET | `reports/sub-ledger` | reports.sub-ledger | `ReportsController@subLedger` | reports.sub-ledger OR reports.* |
| GET | `reports/trial-balance` | reports.trial-balance | `ReportsController@trialBalance` | reports.trial-balance OR reports.* |
| GET | `reports/trial-balance.print` | reports.trial-balance.print | `ReportsController@printTrialBalance` | reports.trial-balance.print OR reports.trial-balance.* |
| GET | `reports/trial-balance/export-csv` | reports.trial-balance.export-csv | `ReportsController@exportTrialBalanceCsv` | reports.trial-balance.export-csv OR reports.trial-balance.* |

### `reset-password` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `reset-password` | password.update | `Auth\PasswordResetController@resetPassword` | NONE — public/pre-auth |
| GET | `reset-password/{token}` | password.reset | `Auth\PasswordResetController@showResetPasswordForm` | NONE — public/pre-auth |

### `salary` (46 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `salary/payroll-elements` | salary.payroll-elements.index | `PayrollElementController@index` | salary.payroll-elements.index OR salary.payroll-elements.* |
| POST | `salary/payroll-elements` | salary.payroll-elements.store | `PayrollElementController@store` | salary.payroll-elements.store OR salary.payroll-elements.* |
| GET | `salary/payroll-elements/create` | salary.payroll-elements.create | `PayrollElementController@create` | salary.payroll-elements.create OR salary.payroll-elements.* |
| PUT|PATCH | `salary/payroll-elements/{payroll_element}` | salary.payroll-elements.update | `PayrollElementController@update` | salary.payroll-elements.update OR salary.payroll-elements.* |
| DELETE | `salary/payroll-elements/{payroll_element}` | salary.payroll-elements.destroy | `PayrollElementController@destroy` | salary.payroll-elements.destroy OR salary.payroll-elements.* |
| GET | `salary/payroll-elements/{payroll_element}/edit` | salary.payroll-elements.edit | `PayrollElementController@edit` | salary.payroll-elements.edit OR salary.payroll-elements.* |
| PUT | `salary/payroll-items/{payroll_item}` | salary.payroll-items.update | `PayrollController@updateItem` | salary.payroll-items.update OR salary.payroll-items.* |
| GET | `salary/payroll-items/{payroll_item}/edit` | salary.payroll-items.edit | `PayrollController@editItem` | salary.payroll-items.edit OR salary.payroll-items.* |
| GET | `salary/payrolls` | salary.payrolls.index | `PayrollController@index` | salary.payrolls.index OR salary.payrolls.* |
| GET | `salary/payrolls/dashboard` | salary.payrolls.dashboard | `PayrollController@dashboard` | salary.payrolls.dashboard OR salary.payrolls.* |
| GET | `salary/payrolls/{payroll}` | salary.payrolls.show | `PayrollController@show` | salary.payrolls.show OR salary.payrolls.* |
| DELETE | `salary/payrolls/{payroll}` | salary.payrolls.destroy | `PayrollController@destroy` | salary.payrolls.destroy OR salary.payrolls.* |
| PATCH | `salary/payrolls/{payroll}/approve` | salary.payrolls.transition.pending-manager-approval-to-approved | `PayrollController@approve` | salary.payrolls.transition.pending-manager-approval-to-approved OR salary.payrolls.transition.* |
| PATCH | `salary/payrolls/{payroll}/mark-paid` | salary.payrolls.transition.approved-to-paid | `PayrollController@markPaid` | salary.payrolls.transition.approved-to-paid OR salary.payrolls.transition.* |
| PATCH | `salary/payrolls/{payroll}/submit-for-approval` | salary.payrolls.transition.draft-to-pending-manager-approval | `PayrollController@submitForApproval` | salary.payrolls.transition.draft-to-pending-manager-approval OR salary.payrolls.transition.* |
| GET | `salary/public-holidays` | salary.public-holidays.index | `PublicHolidayController@index` | salary.public-holidays.index OR salary.public-holidays.* |
| POST | `salary/public-holidays` | salary.public-holidays.store | `PublicHolidayController@store` | salary.public-holidays.store OR salary.public-holidays.* |
| GET | `salary/public-holidays/create` | salary.public-holidays.create | `PublicHolidayController@create` | salary.public-holidays.create OR salary.public-holidays.* |
| PUT|PATCH | `salary/public-holidays/{public_holiday}` | salary.public-holidays.update | `PublicHolidayController@update` | salary.public-holidays.update OR salary.public-holidays.* |
| DELETE | `salary/public-holidays/{public_holiday}` | salary.public-holidays.destroy | `PublicHolidayController@destroy` | salary.public-holidays.destroy OR salary.public-holidays.* |
| GET | `salary/public-holidays/{public_holiday}/edit` | salary.public-holidays.edit | `PublicHolidayController@edit` | salary.public-holidays.edit OR salary.public-holidays.* |
| GET | `salary/salary-decrees` | salary.salary-decrees.index | `SalaryDecreeController@index` | salary.salary-decrees.index OR salary.salary-decrees.* |
| POST | `salary/salary-decrees` | salary.salary-decrees.store | `SalaryDecreeController@store` | salary.salary-decrees.store OR salary.salary-decrees.* |
| GET | `salary/salary-decrees/create` | salary.salary-decrees.create | `SalaryDecreeController@create` | salary.salary-decrees.create OR salary.salary-decrees.* |
| PUT|PATCH | `salary/salary-decrees/{salary_decree}` | salary.salary-decrees.update | `SalaryDecreeController@update` | salary.salary-decrees.update OR salary.salary-decrees.* |
| DELETE | `salary/salary-decrees/{salary_decree}` | salary.salary-decrees.destroy | `SalaryDecreeController@destroy` | salary.salary-decrees.destroy OR salary.salary-decrees.* |
| GET | `salary/salary-decrees/{salary_decree}/edit` | salary.salary-decrees.edit | `SalaryDecreeController@edit` | salary.salary-decrees.edit OR salary.salary-decrees.* |
| GET | `salary/tax-slabs` | salary.tax-slabs.index | `TaxSlabController@index` | salary.tax-slabs.index OR salary.tax-slabs.* |
| POST | `salary/tax-slabs` | salary.tax-slabs.store | `TaxSlabController@store` | salary.tax-slabs.store OR salary.tax-slabs.* |
| GET | `salary/tax-slabs/create` | salary.tax-slabs.create | `TaxSlabController@create` | salary.tax-slabs.create OR salary.tax-slabs.* |
| GET | `salary/tax-slabs/{tax_slab}` | salary.tax-slabs.show | `TaxSlabController@show` | salary.tax-slabs.show OR salary.tax-slabs.* |
| PUT|PATCH | `salary/tax-slabs/{tax_slab}` | salary.tax-slabs.update | `TaxSlabController@update` | salary.tax-slabs.update OR salary.tax-slabs.* |
| DELETE | `salary/tax-slabs/{tax_slab}` | salary.tax-slabs.destroy | `TaxSlabController@destroy` | salary.tax-slabs.destroy OR salary.tax-slabs.* |
| GET | `salary/tax-slabs/{tax_slab}/edit` | salary.tax-slabs.edit | `TaxSlabController@edit` | salary.tax-slabs.edit OR salary.tax-slabs.* |
| GET | `salary/work-site-contracts` | salary.work-site-contracts.index | `WorkSiteContractController@index` | salary.work-site-contracts.index OR salary.work-site-contracts.* |
| POST | `salary/work-site-contracts` | salary.work-site-contracts.store | `WorkSiteContractController@store` | salary.work-site-contracts.store OR salary.work-site-contracts.* |
| GET | `salary/work-site-contracts/create` | salary.work-site-contracts.create | `WorkSiteContractController@create` | salary.work-site-contracts.create OR salary.work-site-contracts.* |
| PUT|PATCH | `salary/work-site-contracts/{work_site_contract}` | salary.work-site-contracts.update | `WorkSiteContractController@update` | salary.work-site-contracts.update OR salary.work-site-contracts.* |
| DELETE | `salary/work-site-contracts/{work_site_contract}` | salary.work-site-contracts.destroy | `WorkSiteContractController@destroy` | salary.work-site-contracts.destroy OR salary.work-site-contracts.* |
| GET | `salary/work-site-contracts/{work_site_contract}/edit` | salary.work-site-contracts.edit | `WorkSiteContractController@edit` | salary.work-site-contracts.edit OR salary.work-site-contracts.* |
| GET | `salary/work-sites` | salary.work-sites.index | `WorkSiteController@index` | salary.work-sites.index OR salary.work-sites.* |
| POST | `salary/work-sites` | salary.work-sites.store | `WorkSiteController@store` | salary.work-sites.store OR salary.work-sites.* |
| GET | `salary/work-sites/create` | salary.work-sites.create | `WorkSiteController@create` | salary.work-sites.create OR salary.work-sites.* |
| PUT|PATCH | `salary/work-sites/{work_site}` | salary.work-sites.update | `WorkSiteController@update` | salary.work-sites.update OR salary.work-sites.* |
| DELETE | `salary/work-sites/{work_site}` | salary.work-sites.destroy | `WorkSiteController@destroy` | salary.work-sites.destroy OR salary.work-sites.* |
| GET | `salary/work-sites/{work_site}/edit` | salary.work-sites.edit | `WorkSiteController@edit` | salary.work-sites.edit OR salary.work-sites.* |

### `service-groups` (7 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `service-groups` | service-groups.index | `ServiceGroupController@index` | service-groups.index OR service-groups.* |
| POST | `service-groups` | service-groups.store | `ServiceGroupController@store` | service-groups.store OR service-groups.* |
| GET | `service-groups/create` | service-groups.create | `ServiceGroupController@create` | service-groups.create OR service-groups.* |
| GET | `service-groups/{service_group}` | service-groups.show | `ServiceGroupController@show` | service-groups.show OR service-groups.* |
| PUT|PATCH | `service-groups/{service_group}` | service-groups.update | `ServiceGroupController@update` | service-groups.update OR service-groups.* |
| DELETE | `service-groups/{service_group}` | service-groups.destroy | `ServiceGroupController@destroy` | service-groups.destroy OR service-groups.* |
| GET | `service-groups/{service_group}/edit` | service-groups.edit | `ServiceGroupController@edit` | service-groups.edit OR service-groups.* |

### `services` (11 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `services` | services.index | `ServiceController@index` | services.index OR services.* |
| POST | `services` | services.store | `ServiceController@store` | services.store OR services.* |
| GET | `services/create` | services.create | `ServiceController@create` | services.create OR services.* |
| GET | `services/export` | services.export | `ServiceController@export` | services.export OR services.* |
| GET | `services/import` | services.import | `ServiceController@importForm` | services.import OR services.* |
| POST | `services/import` | services.import.store | `ServiceController@import` | services.import.store OR services.import.* |
| GET | `services/search-service-group` | services.search-service-group | `ServiceController@searchServiceGroup` | services.search-service-group OR services.* |
| GET | `services/{service}` | services.show | `ServiceController@show` | services.show OR services.* |
| PUT|PATCH | `services/{service}` | services.update | `ServiceController@update` | services.update OR services.* |
| DELETE | `services/{service}` | services.destroy | `ServiceController@destroy` | services.destroy OR services.* |
| GET | `services/{service}/edit` | services.edit | `ServiceController@edit` | services.edit OR services.* |

### `settlements` (4 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `settlements/cheques/{cheque}` | settlements.store-for-cheque | `SettlementController@storeForCheque` | settlements.store-for-cheque OR settlements.* |
| POST | `settlements/installments/{installment}/payments` | settlements.installments.record-payment | `SettlementController@recordPayment` | settlements.installments.record-payment OR settlements.installments.* |
| GET | `settlements/{obligation}` | settlements.show | `SettlementController@show` | settlements.show OR settlements.* |
| POST | `settlements/{obligation}/installments` | settlements.installments.store | `SettlementController@storeInstallments` | settlements.installments.store OR settlements.installments.* |

### `subjects` (10 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `subjects` | subjects.index | `SubjectController@index` | subjects.index OR subjects.* |
| POST | `subjects` | subjects.store | `SubjectController@store` | subjects.store OR subjects.* |
| GET | `subjects/create` | subjects.create | `SubjectController@create` | subjects.create OR subjects.* |
| GET | `subjects/search` | subjects.search | `SubjectController@search` | subjects.search OR subjects.* |
| GET | `subjects/search-code` | subjects.search-code | `SubjectController@searchCode` | subjects.search-code OR subjects.* |
| POST | `subjects/transfer` | subjects.transfer | `SubjectController@transferSubject` | subjects.transfer OR subjects.* |
| GET | `subjects/{subject}` | subjects.show | `SubjectController@show` | subjects.show OR subjects.* |
| PUT|PATCH | `subjects/{subject}` | subjects.update | `SubjectController@update` | subjects.update OR subjects.* |
| DELETE | `subjects/{subject}` | subjects.destroy | `SubjectController@destroy` | subjects.destroy OR subjects.* |
| GET | `subjects/{subject}/edit` | subjects.edit | `SubjectController@edit` | subjects.edit OR subjects.* |

### `sync-conflicts` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `sync-conflicts` | sync-conflicts.index | `SyncConflictController@index` | sync-conflicts.index OR sync-conflicts.* |
| POST | `sync-conflicts/{conflict}/resolve` | sync-conflicts.resolve | `SyncConflictController@resolve` | sync-conflicts.resolve OR sync-conflicts.* |

### `transactions` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `transactions` | transactions.index | `TransactionController@index` | transactions.index OR transactions.* |
| GET | `transactions/{transaction}` | transactions.show | `TransactionController@show` | transactions.show OR transactions.* |

### `translations` (12 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `translations/add/{groupKey}` |  | `Barryvdh\TranslationManager\Controller@postAdd` | access-super-admin-panel |
| POST | `translations/delete/{groupKey}/{translationKey}` |  | `Barryvdh\TranslationManager\Controller@postDelete` | access-super-admin-panel |
| POST | `translations/edit/{groupKey}` |  | `Barryvdh\TranslationManager\Controller@postEdit` | access-super-admin-panel |
| POST | `translations/find` |  | `Barryvdh\TranslationManager\Controller@postFind` | access-super-admin-panel |
| POST | `translations/groups/add` |  | `Barryvdh\TranslationManager\Controller@postAddGroup` | access-super-admin-panel |
| POST | `translations/import` |  | `Barryvdh\TranslationManager\Controller@postImport` | access-super-admin-panel |
| POST | `translations/locales/add` |  | `Barryvdh\TranslationManager\Controller@postAddLocale` | access-super-admin-panel |
| POST | `translations/locales/remove` |  | `Barryvdh\TranslationManager\Controller@postRemoveLocale` | access-super-admin-panel |
| POST | `translations/publish/{groupKey}` |  | `Barryvdh\TranslationManager\Controller@postPublish` | access-super-admin-panel |
| POST | `translations/translate-missing` |  | `Barryvdh\TranslationManager\Controller@postTranslateMissing` | access-super-admin-panel |
| GET | `translations/view/{groupKey?}` |  | `Barryvdh\TranslationManager\Controller@getView` | access-super-admin-panel |
| GET | `translations/{groupKey?}` |  | `Barryvdh\TranslationManager\Controller@getIndex` | access-super-admin-panel |

### `up` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `up` |  | `Closure` | NONE — public/pre-auth |

### `verification-notification` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `verification-notification` | verification.send | `Auth\RegisterController@resendVerificationNotification` | NONE — auth only |

### `verify` (2 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `verify` | verification.notice | `Auth\RegisterController@showVerificationNotice` | NONE — auth only |
| GET | `verify/{id}/{hash}` | verification.verify | `Auth\RegisterController@verify` | NONE — auth only |

### `verify-otp` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| POST | `verify-otp` | verification.otp | `Auth\RegisterController@verifyOtp` | NONE — auth only |

### `warehouse` (1 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `warehouse/dashboard` | warehouse.dashboard | `WarehouseDashboardController@index` | warehouse.dashboard OR warehouse.* |

### `warehouses` (11 routes)

| Method | URI | Route Name | Controller@Action | Effective Permission |
|---|---|---|---|---|
| GET | `warehouses` | warehouses.index | `WarehouseController@index` | warehouses.index OR warehouses.* |
| POST | `warehouses` | warehouses.store | `WarehouseController@store` | warehouses.store OR warehouses.* |
| GET | `warehouses/create` | warehouses.create | `WarehouseController@create` | warehouses.create OR warehouses.* |
| GET | `warehouses/search-products` | warehouses.search-products | `WarehouseController@searchProducts` | warehouses.search-products OR warehouses.* |
| GET | `warehouses/transfer` | warehouses.transfer | `WarehouseController@transferForm` | warehouses.transfer OR warehouses.* |
| POST | `warehouses/transfer` | warehouses.transfer.store | `WarehouseController@transfer` | warehouses.transfer.store OR warehouses.transfer.* |
| GET | `warehouses/transfer-history` | warehouses.transfer-history | `WarehouseController@transferHistory` | warehouses.transfer-history OR warehouses.* |
| GET | `warehouses/{warehouse}` | warehouses.show | `WarehouseController@show` | warehouses.show OR warehouses.* |
| PUT|PATCH | `warehouses/{warehouse}` | warehouses.update | `WarehouseController@update` | warehouses.update OR warehouses.* |
| DELETE | `warehouses/{warehouse}` | warehouses.destroy | `WarehouseController@destroy` | warehouses.destroy OR warehouses.* |
| GET | `warehouses/{warehouse}/edit` | warehouses.edit | `WarehouseController@edit` | warehouses.edit OR warehouses.* |

## §2. AUTH-ONLY routes — reviewed individually, no gap found (10 routes)

Each of these is protected by login but has no permission/role gate beyond that. All 10 were reviewed and confirmed to be routes any authenticated user legitimately needs regardless of role or company membership — not an oversight:

| Route | Why no permission gate is correct |
|---|---|
| `about` | Static informational page, identical for every authenticated user. |
| `account.edit` / `account.update` | Self-service "change my own username/password" — every authenticated user must be able to reach their own account regardless of role (deliberate design, see `CLOUD_AUTONOMOUS_STATE.md` §8). |
| `impersonation.leave` | Returning to one's own admin session after impersonating another user — starting an impersonation already required `users.impersonate`; leaving it is inherently self-scoped and requires no separate permission. |
| `registered-user.company.create` / `registered-user.company.store` | Onboarding: a freshly self-registered user has no company yet, so no company-scoped permission could apply — this is the bootstrap step that creates their first company. |
| `verification.send` / `verification.notice` / `verification.otp` / `verification.verify` | Email/OTP verification flow for the user's own account — inherently self-scoped, predates any role/permission being meaningful for that user. |

## §3. PUBLIC routes (no auth at all) — reviewed individually, no gap found (12 routes)

| Route | Why public access is correct |
|---|---|
| `login` (GET/POST), `logout`, `register`, `register/email`, `locale` | Pre-authentication bootstrap — cannot require login to log in. |
| `forgot-password`, `reset-password` (GET/POST) | Password recovery must work for a user who is, by definition, not logged in. All four routes carry `throttle:6,1`. |
| `api/devices/pair` | D-071 decision 6 (locked): the activation code itself is the single-use, short-lived credential — device pairing is deliberately unauthenticated by design. Carries `throttle:10,1` (Workstream T closure) as defense-in-depth; the 36⁸-keyspace/15-minute-TTL code was explicitly reasoned to be impractical to brute-force even unthrottled. |
| `up` | Laravel's standard framework health-check endpoint (`bootstrap/app.php`'s `health: '/up'`) — must be reachable without auth for infrastructure monitoring. |

**No other route in the application lacks a permission, role, employee, or device-credential gate.** The `barryvdh/laravel-translation-manager` route group (`/translations/*`, 11 routes) previously appeared here as PUBLIC — this was Workstream J's CRITICAL finding #12 (a real, exploitable, unauthenticated-write vulnerability), fixed in the same pass this matrix was produced. See Decision Log's "Ninth continuation pass" and AGENTS.md guardrail #29 for full detail. It now requires `web, auth, permission:access-super-admin-panel` and appears under `translations` in §1 like every other gated route.

## §4. Cross-reference: known findings this matrix already reflects the fix for

- **7 cross-company ownership gaps** (`PaymentAllocationController`, `SettlementController`, `CommentController`, `DocumentFileController`, `TransactionController`, `WorkSiteContractController`, `PayrollController::editItem`) — all fixed prior to this matrix being built (Decision Log "Second continuation pass"); the matrix's `CheckPermission`-derived permission column does NOT by itself capture resource-ownership checks (those are explicit `abort_unless(...)` calls inside the controller method, not middleware), so a route showing a valid permission in §1 is not automatically proof against a resource-ownership gap — see `tests/Feature/CrossCompanyResourceAccessTest.php` for that separate layer of coverage, and §5 below (Workstream U) for the adversarial verification of this exact distinction.
- **Translation-manager unauthenticated access** — CRITICAL finding #12, fixed in this same pass (see §3 above).

## §5. Known limitation of this matrix

This document proves **which Spatie permission (if any) a route's middleware requires** — it does NOT by itself prove that the permission is the *correct, sufficiently narrow* one, nor does it prove resource-level ownership (e.g., that a `documents.show` permission-holder cannot view ANOTHER company's document by id). Those two additional layers are covered separately by:
1. `tests/Feature/CrossCompanyResourceAccessTest.php` (resource-ownership, 9 tests) and `tests/Feature/AdversarialCrossCompanyTest.php` (Workstream U, broader adversarial sweep, added alongside this matrix).
2. Manual review of each permission's seeded role assignments (`RolesAndPermissionsSeeder`) — not re-audited as part of building this matrix; a stale or over-broad role-to-permission assignment would not show up here.

This matrix should be regenerated (rerun the route-enumeration script, `docs/project-context/` scratch tooling) whenever a new route is added, and reconciled against Decision Log entries whenever a new cross-company or permission-bypass finding is fixed, so it does not silently go stale the way the roadmap's own workstream-status table was found to (§38 of the roadmap).
