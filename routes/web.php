<?php

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/login', [Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [Controllers\Auth\LoginController::class, 'login'])->middleware('throttle:6,1');
Route::get('/logout', [Controllers\Auth\LoginController::class, 'logout'])->name('logout');
Route::post('/locale', [Controllers\Auth\LoginController::class, 'locale'])->name('locale');
Route::post('/impersonation/leave', [Controllers\Management\UserController::class, 'leaveImpersonation'])->middleware('auth')->name('impersonation.leave');

Route::get('/forgot-password', [Controllers\Auth\PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [Controllers\Auth\PasswordResetController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [Controllers\Auth\PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [Controllers\Auth\PasswordResetController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');

Route::middleware('ensure-feature-enabled:registration')->group(function () {
    Route::get('/register', [Controllers\Auth\RegisterController::class, 'showRegisterForm'])->name('register');
    Route::post('/register/email', [Controllers\Auth\RegisterController::class, 'registerWithEmail'])->name('register.email');
});
Route::get('/verify', [Controllers\Auth\RegisterController::class, 'showVerificationNotice'])->middleware('auth')->name('verification.notice');
Route::post('/verify-otp', [Controllers\Auth\RegisterController::class, 'verifyOtp'])->middleware(['auth', 'throttle:6,1'])->name('verification.otp');
Route::post('/verification-notification', [Controllers\Auth\RegisterController::class, 'resendVerificationNotification'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');
Route::get('/verify/{id}/{hash}', [Controllers\Auth\RegisterController::class, 'verify'])->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');

Route::middleware(['auth', 'ensure-feature-enabled:email_verification'])->group(function () {
    Route::get('/about', [Controllers\AboutController::class, 'index'])->name('about');
    Route::get('/registered-user/company', [Controllers\CompanyController::class, 'createCompanyForRegisteredUser'])->name('registered-user.company.create');
    Route::post('/registered-user/company', [Controllers\CompanyController::class, 'storeCompanyForRegisteredUser'])->name('registered-user.company.store');
    Route::get('/account', [Controllers\AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [Controllers\AccountController::class, 'update'])->name('account.update');
    Route::patch('/account/ui-mode', [Controllers\AccountController::class, 'updateUiMode'])->name('account.update-ui-mode');
});

Route::middleware(['auth', 'permission:access-super-admin-panel', 'ensure-feature-enabled:email_verification'])->prefix('management')->group(function () {
    Route::get('/', [Controllers\HomeController::class, 'managementDashboard'])->name('management.dashboard');
    Route::get('/activity-logs', [Controllers\Management\ActivityLogController::class, 'index'])
        ->middleware('permission:access-super-admin-panel|activity-logs.index')
        ->name('management.activity-logs.index');
    Route::get('/activity-logs/{activity}/details', [Controllers\Management\ActivityLogController::class, 'details'])
        ->middleware('permission:access-super-admin-panel|activity-logs.details')
        ->whereNumber('activity')->name('management.activity-logs.details');
    Route::get('/settings', [Controllers\AboutController::class, 'index'])->name('management.settings');
    Route::get('/users/{user}', [Controllers\Management\UserController::class, 'show'])->whereNumber('user')->name('users.show');
    Route::post('/users/{user}/verify', [Controllers\Management\UserController::class, 'verify'])->name('users.verify');

    Route::middleware('check-permission')->group(function () {
        Route::put('/settings', [Controllers\AboutController::class, 'updateGlobalConfigs'])->name('update-global-configs');
        Route::resource('permissions', Controllers\Management\PermissionController::class)->except(['show']);
        Route::resource('roles', Controllers\Management\RoleController::class)->except(['show']);
    });
});

Route::group(['middleware' => ['auth', 'ensure-employee', 'ensure-feature-enabled:email_verification'], 'prefix' => 'employee-portal', 'as' => 'employee-portal.'], function () {
    Route::get('/employee', [Controllers\EmployeePortalController::class, 'employeeShow'])->name('employee.show');
    Route::get('/change-employee-information', [Controllers\EmployeePortalController::class, 'changeEmployeeInformation'])->name('change-employee-information');
    Route::put('/change-employee-information', [Controllers\EmployeePortalController::class, 'updateEmployeeInformation'])->name('update-employee-information');
    Route::get('/', [Controllers\EmployeePortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/attendance-logs', [Controllers\EmployeePortalController::class, 'attendanceLogs'])->name('attendance-logs');
    Route::get('/monthly-attendances', [Controllers\EmployeePortalController::class, 'monthlyAttendances'])->name('monthly-attendances');
    Route::get('/monthly-attendances/{monthly_attendance}', [Controllers\EmployeePortalController::class, 'monthlyAttendanceShow'])->name('monthly-attendances.show');
    Route::get('/payrolls', [Controllers\EmployeePortalController::class, 'payrolls'])->name('payrolls');
    Route::get('/payrolls/{payroll}', [Controllers\EmployeePortalController::class, 'payrollShow'])->name('payrolls.show');
    Route::get('/personnel-requests', [Controllers\EmployeePortalController::class, 'personnelRequests'])->name('personnel-requests.index');
    Route::get('/personnel-requests/create', [Controllers\EmployeePortalController::class, 'createPersonnelRequest'])->name('personnel-requests.create');
    Route::post('/personnel-requests', [Controllers\EmployeePortalController::class, 'storePersonnelRequest'])->name('personnel-requests.store');
    Route::get('/personnel-requests/{personnel_request}/edit', [Controllers\EmployeePortalController::class, 'editPersonnelRequest'])->name('personnel-requests.edit');
    Route::put('/personnel-requests/{personnel_request}', [Controllers\EmployeePortalController::class, 'updatePersonnelRequest'])->name('personnel-requests.update');
    Route::delete('/personnel-requests/{personnel_request}', [Controllers\EmployeePortalController::class, 'destroyPersonnelRequest'])->name('personnel-requests.destroy');
});

Route::group(['middleware' => ['auth', 'check-permission', 'ensure-feature-enabled:email_verification']], function () {
    Route::get('api-tokens', [Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::get('api-tokens/create', [Controllers\ApiTokenController::class, 'create'])->name('api-tokens.create');
    Route::post('api-tokens', [Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('api-tokens/{tokenId}', [Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::get('change-company/{company}', [Controllers\CompanyController::class, 'setActiveCompany'])->name('change-company');

    Route::group(['prefix' => 'backups', 'as' => 'backups.'], function () {
        Route::get('/create', [Controllers\BackupController::class, 'create'])->name('create');
        Route::get('/upload', [Controllers\BackupController::class, 'upload'])->name('upload');
        Route::get('/document-files-size', [Controllers\BackupController::class, 'documentFilesSize'])->name('document-files-size');
        Route::post('/export', [Controllers\BackupController::class, 'export'])->name('export');
        Route::post('/import', [Controllers\BackupController::class, 'import'])->name('import');
    });
    Route::get('/', [Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/home/today', [Controllers\HomeController::class, 'today'])->name('home.today');
    // Phase U: guided "Collect money" / "Pay suppliers" (submits to the existing invoices.payments.store).
    Route::get('/quick-payment/{direction}', [Controllers\QuickPaymentController::class, 'create'])->where('direction', 'receive|pay')->name('quick-payment.create');
    Route::post('/quick-payment/{direction}', [Controllers\QuickPaymentController::class, 'store'])->where('direction', 'receive|pay')->name('quick-payment.store');
    Route::get('/home/summary/{metric}', [Controllers\HomeController::class, 'summaryMetric'])->name('home.summary');
    Route::post('/report', [Controllers\ReportEmailController::class, 'store'])->name('report');
    Route::get('subjects/search', [Controllers\SubjectController::class, 'search'])->name('subjects.search');
    Route::get('subjects/search-code', [Controllers\SubjectController::class, 'searchCode'])->name('subjects.search-code');
    Route::post('subjects/transfer', [Controllers\SubjectController::class, 'transferSubject'])->name('subjects.transfer');
    Route::resource('subjects', Controllers\SubjectController::class);
    Route::post('documents/{document}/change-status', [Controllers\DocumentController::class, 'changeStatus'])->name('documents.change-status');
    Route::post('documents/approve-all', [Controllers\DocumentController::class, 'approveAll'])->name('documents.approve-all');
    Route::get('documents/search-account-balance', [Controllers\DocumentController::class, 'searchAccountBalance'])->name('documents.search-account-balance');
    Route::get('documents/sort-numbers', [Controllers\DocumentController::class, 'sortNumbers'])->name('documents.sort-numbers');
    Route::post('documents/sort-numbers/start', [Controllers\DocumentController::class, 'startSorting'])->name('documents.sort-numbers.start');
    Route::post('documents/sort-numbers/process', [Controllers\DocumentController::class, 'processSorting'])->name('documents.sort-numbers.process');
    Route::get('documents/export', [Controllers\DocumentController::class, 'exportForm'])->name('documents.export');
    Route::post('documents/export', [Controllers\DocumentController::class, 'export'])->name('documents.export.download');
    Route::get('documents/import', [Controllers\DocumentController::class, 'importForm'])->name('documents.import');
    Route::post('documents/import', [Controllers\DocumentController::class, 'import'])->name('documents.import.store');
    Route::resource('documents', Controllers\DocumentController::class);
    Route::get('documents/{document}/print', [Controllers\DocumentController::class, 'print'])->name('documents.print');
    Route::get('documents/{document}/duplicate', [Controllers\DocumentController::class, 'duplicate'])->name('documents.duplicate');
    Route::post('documents/{document}/transfer', [Controllers\DocumentController::class, 'transfer'])->name('documents.transfer');

    Route::resource('transactions', Controllers\TransactionController::class)->only(['index', 'show']);
    Route::get('products/search-product-group', [Controllers\ProductController::class, 'searchProductGroup'])->name('products.search-product-group');
    Route::get('products/report', [Controllers\ProductController::class, 'report'])->name('products.report');
    Route::get('products/export', [Controllers\ProductController::class, 'export'])->name('products.export');
    Route::get('products/import', [Controllers\ProductController::class, 'importForm'])->name('products.import');
    Route::post('products/import', [Controllers\ProductController::class, 'import'])->name('products.import.store');
    Route::post('products/{product}/recalculate-quantity', [Controllers\ProductController::class, 'recalculateQuantity'])->name('products.recalculate-quantity');
    Route::resource('products', Controllers\ProductController::class);
    Route::get('warehouses/transfer', [Controllers\WarehouseController::class, 'transferForm'])->name('warehouses.transfer');
    Route::get('warehouses/search-products', [Controllers\WarehouseController::class, 'searchProducts'])->name('warehouses.search-products');
    Route::post('warehouses/transfer', [Controllers\WarehouseController::class, 'transfer'])->name('warehouses.transfer.store');
    Route::get('warehouses/transfer-history', [Controllers\WarehouseController::class, 'transferHistory'])->name('warehouses.transfer-history');
    Route::resource('warehouses', Controllers\WarehouseController::class);
    Route::resource('product-groups', Controllers\ProductGroupController::class);
    Route::get('services/search-service-group', [Controllers\ServiceController::class, 'searchServiceGroup'])->name('services.search-service-group');
    Route::get('services/export', [Controllers\ServiceController::class, 'export'])->name('services.export');
    Route::get('services/import', [Controllers\ServiceController::class, 'importForm'])->name('services.import');
    Route::post('services/import', [Controllers\ServiceController::class, 'import'])->name('services.import.store');
    Route::resource('services', Controllers\ServiceController::class);
    Route::resource('service-groups', Controllers\ServiceGroupController::class);
    Route::get('crm/dashboard', [Controllers\CrmDashboardController::class, 'index'])->name('crm.dashboard');
    Route::get('customers/export', [Controllers\CustomerController::class, 'export'])->name('customers.export');
    Route::get('customers/import', [Controllers\CustomerController::class, 'importForm'])->name('customers.import');
    Route::post('customers/import', [Controllers\CustomerController::class, 'import'])->name('customers.import.store');
    Route::resource('customers', Controllers\CustomerController::class);
    Route::resource('customer-groups', Controllers\CustomerGroupController::class);
    Route::get('bank-accounts/search-bank', [Controllers\BankAccountController::class, 'searchBank'])->name('bank-accounts.search-bank');
    Route::resource('bank-accounts', Controllers\BankAccountController::class);
    Route::resource('banks', Controllers\BankController::class);

    Route::resource('chequebooks', Controllers\ChequebookController::class)->names('chequebooks');
    Route::get('cheques/report', [Controllers\ChequeController::class, 'report'])->name('cheques.report');
    Route::post('cheques/{cheque}/transition/{action}', [Controllers\ChequeController::class, 'transition'])->name('cheques.transition');
    Route::resource('cheques', Controllers\ChequeController::class);

    Route::get('invoices/dashboard', [Controllers\InvoiceDashboardController::class, 'index'])->name('invoices.dashboard');
    Route::get('invoices/search/{invoice_type}', [Controllers\InvoiceController::class, 'search'])->name('invoices.search');
    Route::get('invoices/get-items/{invoice}', [Controllers\InvoiceController::class, 'getItems'])->name('invoices.get-items');
    Route::get('invoices/search-customer', [Controllers\InvoiceController::class, 'searchCustomer'])->name('invoices.search-customer');
    Route::get('invoices/search-product-service', [Controllers\InvoiceController::class, 'searchProductService'])->name('invoices.search-product-service');

    Route::get('invoices/inactive', [Controllers\InvoiceController::class, 'inactiveInvoices'])->name('invoices.inactive');
    Route::post('invoices/inactive/approve', [Controllers\InvoiceController::class, 'approveInactiveInvoices'])->name('invoices.inactive.approve');
    Route::get('invoices/export', [Controllers\InvoiceController::class, 'export'])->name('invoices.export');
    Route::prefix('invoices')->group(function () {
        Route::get('ancillary-costs/search-customer', [Controllers\AncillaryCostController::class, 'searchCustomer'])->name('ancillary-costs.search-customer');
        Route::get('ancillary-costs/search-invoice', [Controllers\AncillaryCostController::class, 'searchInvoice'])->name('ancillary-costs.search-invoice');
        Route::get('ancillary-costs/get-products/{invoice_id}', [Controllers\AncillaryCostController::class, 'getBuyInvoiceProducts'])->name('ancillary-costs.get-products');
        Route::post('ancillary-costs/{ancillary_cost}/change-status/{status}', [Controllers\AncillaryCostController::class, 'changeStatus'])->name('ancillary-costs.change-status');
        Route::patch('ancillary-costs/{ancillary_cost}/tax-deductible-status', [Controllers\AncillaryCostController::class, 'setTaxDeductibleStatus'])->name('ancillary-costs.set-tax-deductible-status');
        Route::post('ancillary-costs/{ancillary_cost}/tax-evidences', [Controllers\TaxEvidenceController::class, 'storeForAncillaryCost'])->name('tax-evidences.store');
        Route::post('{invoice}/tax-evidences', [Controllers\TaxEvidenceController::class, 'storeForInvoice'])->name('invoices.tax-evidences.store');
        Route::get('tax-evidences/{tax_evidence}/download', [Controllers\TaxEvidenceController::class, 'download'])->name('tax-evidences.download');
        Route::delete('tax-evidences/{tax_evidence}', [Controllers\TaxEvidenceController::class, 'destroy'])->name('tax-evidences.destroy');
        Route::get('ancillary-costs/', [Controllers\AncillaryCostController::class, 'index'])->name('ancillary-costs.index');
        Route::get('{invoice}/ancillary-costs/create', [Controllers\AncillaryCostController::class, 'create'])->name('invoices.ancillary-costs.create');
        Route::post('{invoice}/ancillary-costs', [Controllers\AncillaryCostController::class, 'store'])->name('invoices.ancillary-costs.store');
        Route::get('{invoice}/ancillary-costs/{ancillary_cost}', [Controllers\AncillaryCostController::class, 'show'])->name('invoices.ancillary-costs.show');
        Route::get('{invoice}/ancillary-costs/{ancillary_cost}/edit', [Controllers\AncillaryCostController::class, 'edit'])->name('invoices.ancillary-costs.edit');
        Route::put('{invoice}/ancillary-costs/{ancillary_cost}', [Controllers\AncillaryCostController::class, 'update'])->name('invoices.ancillary-costs.update');
        Route::delete('{invoice}/ancillary-costs/{ancillary_cost}', [Controllers\AncillaryCostController::class, 'destroy'])->name('invoices.ancillary-costs.destroy');
    });
    Route::get('invoices/moadian-histories', [Controllers\MoadianHistoryController::class, 'index'])->name('invoices.moadian-histories.index');
    Route::get('invoices/moadian-queue', [Controllers\MoadianWorkQueueController::class, 'index'])->name('invoices.moadian-queue.index');
    Route::resource('invoices', Controllers\InvoiceController::class);
    Route::get('invoices/{invoice}/moadian-histories', [Controllers\MoadianHistoryController::class, 'show'])->name('invoices.moadian-histories.show');
    Route::post('invoices/{invoice}/send-moadian', [Controllers\InvoiceController::class, 'sendMoadian'])->name('invoices.send-moadian');
    Route::post('invoices/{invoice}/moadian-check-status', [Controllers\MoadianHistoryController::class, 'checkStatus'])->name('invoices.moadian-check-status');
    Route::get('invoices/{invoice}/conflicts', [Controllers\InvoiceController::class, 'conflicts'])->name('invoices.conflicts');
    Route::get('invoices/{invoice}/conflicts/{type}', [Controllers\InvoiceController::class, 'showMoreConflictsByType'])->name('invoices.conflicts.more');
    Route::post('invoices/{invoice}/group-action', [Controllers\InvoiceController::class, 'groupAction'])->name('invoices.group-action');
    Route::get('invoices/{invoice}/print', [Controllers\InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('invoices/{invoice}/void', [Controllers\InvoiceController::class, 'showVoidForm'])->name('invoices.void-form');
    Route::post('invoices/{invoice}/void', [Controllers\InvoiceController::class, 'voidInvoice'])->name('invoices.void');
    Route::post('invoices/{invoice}/change-status/{status}', [Controllers\InvoiceController::class, 'changeStatus'])->name('invoices.change-status');
    Route::post('invoices/{invoice}/payments', [Controllers\PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('invoices/{invoice}/payments/cheque', [Controllers\PaymentController::class, 'storeCheque'])->name('invoices.payments.store-cheque');
    Route::delete('invoices/{invoice}/payments/{payment}', [Controllers\PaymentController::class, 'destroy'])->name('invoices.payments.destroy');
    Route::post('invoices/{invoice}/payments/{payment}/document', [Controllers\PaymentController::class, 'createDocument'])->name('invoices.payments.create-document');
    Route::delete('invoices/{invoice}/payments/{payment}/document', [Controllers\PaymentController::class, 'destroyDocument'])->name('invoices.payments.destroy-document');
    Route::post('customers/{customer}/credit-payments', [Controllers\PaymentAllocationController::class, 'storeCreditPayment'])->name('customers.credit-payments.store');
    Route::post('payments/{payment}/allocations', [Controllers\PaymentAllocationController::class, 'allocate'])->name('payments.allocations.store');
    Route::delete('payments/{payment}/allocations/{allocation}', [Controllers\PaymentAllocationController::class, 'destroy'])->name('payments.allocations.destroy');
    Route::post('invoices/{invoice}/transfer', [Controllers\InvoiceController::class, 'transfer'])->name('invoices.transfer');
    Route::group(['prefix' => 'management'], function () {
        Route::resource('companies', Controllers\CompanyController::class);
        Route::post('companies/close-fiscal-year/{company}', [Controllers\CompanyController::class, 'closeFiscalYear'])->name('companies.close-fiscal-year');
        Route::get('companies/{company}/closing-wizard', [Controllers\CompanyController::class, 'closingWizard'])->name('companies.closing-wizard');
        Route::post('companies/{company}/closing-wizard/step1', [Controllers\CompanyController::class, 'closingWizardStep1'])->name('companies.closing-wizard.step1');
        Route::post('companies/{company}/closing-wizard/step3', [Controllers\CompanyController::class, 'closingWizardStep3'])->name('companies.closing-wizard.step3');
        Route::post('users/{user}/create-employee', [Controllers\Management\UserController::class, 'createEmployee'])
            ->name('users.create-employee');
        Route::post('users/{user}/impersonate', [Controllers\Management\UserController::class, 'impersonate'])
            ->name('users.impersonate');
        Route::resource('users', Controllers\Management\UserController::class)->except(['show']);
        // ConfigController only implements index/edit/update; the other resource routes 500'd (missing methods).
        Route::resource('configs', Controllers\ConfigController::class)->only(['index', 'edit', 'update']);
        Route::get('workflow-governance', [Controllers\WorkflowGovernanceController::class, 'edit'])->name('workflow-governance.edit');
        Route::put('workflow-governance', [Controllers\WorkflowGovernanceController::class, 'update'])->name('workflow-governance.update');
        Route::get('tax-center', [Controllers\TaxCenterController::class, 'index'])->name('tax-center.index');
        Route::get('tax-center/audit-pack', [Controllers\TaxAuditPackController::class, 'download'])->name('tax-center.audit-pack');
        Route::get('tax-center/prepare', [Controllers\TaxFilingPreparationController::class, 'index'])->name('tax-center.prepare');
        Route::get('tax-readiness', [Controllers\TaxReadinessController::class, 'index'])->name('tax-readiness.index');
        Route::post('tax-readiness/recalculate', [Controllers\TaxReadinessController::class, 'recalculate'])->name('tax-readiness.recalculate');

        // P12.2 (Tax Alert & Smart Notification Engine).
        Route::get('tax-alerts', [Controllers\TaxAlertController::class, 'index'])->name('tax-alerts.index');
        Route::post('tax-alerts/read-all', [Controllers\TaxAlertController::class, 'readAll'])->name('tax-alerts.read-all');
        Route::post('tax-alerts/{tax_alert}/read', [Controllers\TaxAlertController::class, 'read'])->name('tax-alerts.read');

        // P12.3 (Seasonal Tax & VAT Reporting Engine).
        Route::get('seasonal-tax', [Controllers\SeasonalTaxController::class, 'index'])->name('seasonal-tax.index');
        Route::get('seasonal-tax/export-csv', [Controllers\SeasonalTaxController::class, 'exportCsv'])->name('seasonal-tax.export-csv');
        Route::get('seasonal-tax/export-pdf', [Controllers\SeasonalTaxController::class, 'exportPdf'])->name('seasonal-tax.export-pdf');

        // P12.4 (Field Operations Commission & Route Analytics Engine).
        Route::get('field-operations-analytics', [Controllers\FieldOperationsAnalyticsController::class, 'index'])->name('field-operations-analytics.index');
        Route::post('field-operations-analytics/recalculate', [Controllers\FieldOperationsAnalyticsController::class, 'recalculate'])->name('field-operations-analytics.recalculate');

        // Industry Profiles (per-trade settings + calculators; definitions are data files).
        Route::get('industry-profiles', [Controllers\IndustryProfileController::class, 'index'])->name('industry-profiles.index');
        Route::get('industry-profiles/{profile}', [Controllers\IndustryProfileController::class, 'show'])->where('profile', '[a-z][a-z0-9_]*')->name('industry-profiles.show');
        Route::put('industry-profiles/{profile}', [Controllers\IndustryProfileController::class, 'update'])->where('profile', '[a-z][a-z0-9_]*')->name('industry-profiles.update');
        Route::post('industry-profiles/{profile}/toggle', [Controllers\IndustryProfileController::class, 'toggle'])->where('profile', '[a-z][a-z0-9_]*')->name('industry-profiles.toggle');
        Route::post('industry-profiles/{profile}/settings/{setting}/confirm', [Controllers\IndustryProfileController::class, 'confirm'])->where(['profile' => '[a-z][a-z0-9_]*', 'setting' => '[a-z][a-z0-9_]*'])->name('industry-profiles.confirm');
        Route::post('industry-profiles/{profile}/calculators/{calculator}', [Controllers\IndustryProfileController::class, 'calculate'])->where(['profile' => '[a-z][a-z0-9_]*', 'calculator' => '[a-z][a-z0-9_]*'])->name('industry-profiles.calculate');

        // P11 (Field Sales & Distribution -- "FieldOperations"). Backend
        // only for this pass -- JSON endpoints matching the same style
        // P3's Currency/PaymentTerm controllers established (no dedicated
        // Blade UI yet, consumed by future API/mobile clients).
        Route::get('sales-routes', [Controllers\SalesRouteController::class, 'index'])->name('sales-routes.index');
        Route::post('sales-routes', [Controllers\SalesRouteController::class, 'store'])->name('sales-routes.store');
        Route::put('sales-routes/{sales_route}', [Controllers\SalesRouteController::class, 'update'])->name('sales-routes.update');
        Route::delete('sales-routes/{sales_route}', [Controllers\SalesRouteController::class, 'destroy'])->name('sales-routes.destroy');
        Route::post('sales-routes/{sales_route}/customers', [Controllers\SalesRouteController::class, 'assignCustomer'])->name('sales-routes.assign-customer');
        Route::delete('sales-routes/{sales_route}/customers/{customer}', [Controllers\SalesRouteController::class, 'removeCustomer'])->name('sales-routes.remove-customer');

        Route::get('visit-plans', [Controllers\VisitPlanController::class, 'index'])->name('visit-plans.index');
        Route::post('visit-plans', [Controllers\VisitPlanController::class, 'store'])->name('visit-plans.store');
        Route::patch('visit-plans/{visit_plan}/status', [Controllers\VisitPlanController::class, 'updateStatus'])->name('visit-plans.update-status');
        Route::delete('visit-plans/{visit_plan}', [Controllers\VisitPlanController::class, 'destroy'])->name('visit-plans.destroy');

        Route::get('promotions', [Controllers\PromotionController::class, 'index'])->name('promotions.index');
        Route::post('promotions', [Controllers\PromotionController::class, 'store'])->name('promotions.store');
        Route::put('promotions/{promotion}', [Controllers\PromotionController::class, 'update'])->name('promotions.update');
        Route::delete('promotions/{promotion}', [Controllers\PromotionController::class, 'destroy'])->name('promotions.destroy');
        Route::post('promotions/evaluate', [Controllers\PromotionController::class, 'evaluate'])->name('promotions.evaluate');

        Route::get('daily-settlements', [Controllers\DailySettlementController::class, 'index'])->name('daily-settlements.index');
        Route::post('daily-settlements', [Controllers\DailySettlementController::class, 'store'])->name('daily-settlements.store');
        Route::post('daily-settlements/{daily_settlement}/submit', [Controllers\DailySettlementController::class, 'submit'])->name('daily-settlements.submit');
        Route::post('daily-settlements/{daily_settlement}/approve', [Controllers\DailySettlementController::class, 'approve'])->name('daily-settlements.approve');

        // Capillary distribution (پخش مویرگی): screens over the P11 backend above (their JSON API is unchanged).
        Route::prefix('distribution')->group(function () {
            Route::get('/', [Controllers\DistributionHubController::class, 'index'])->name('distribution.index');

            Route::get('routes', [Controllers\DistributionRouteController::class, 'index'])->name('distribution.routes.index');
            Route::post('routes', [Controllers\DistributionRouteController::class, 'store'])->name('distribution.routes.store');
            Route::put('routes/{sales_route}', [Controllers\DistributionRouteController::class, 'update'])->name('distribution.routes.update');
            Route::delete('routes/{sales_route}', [Controllers\DistributionRouteController::class, 'destroy'])->name('distribution.routes.destroy');
            Route::post('routes/{sales_route}/customers', [Controllers\DistributionRouteController::class, 'assignCustomer'])->name('distribution.routes.customers.store');
            Route::delete('routes/{sales_route}/customers/{customer}', [Controllers\DistributionRouteController::class, 'removeCustomer'])->name('distribution.routes.customers.destroy');

            Route::get('visitors', [Controllers\DistributionVisitorController::class, 'index'])->name('distribution.visitors.index');
            Route::post('visitors', [Controllers\DistributionVisitorController::class, 'store'])->name('distribution.visitors.store');
            Route::put('visitors/{visitor}', [Controllers\DistributionVisitorController::class, 'update'])->name('distribution.visitors.update');
            Route::delete('visitors/{visitor}', [Controllers\DistributionVisitorController::class, 'destroy'])->name('distribution.visitors.destroy');

            Route::get('plans', [Controllers\DistributionPlanController::class, 'index'])->name('distribution.plans.index');
            Route::post('plans', [Controllers\DistributionPlanController::class, 'store'])->name('distribution.plans.store');
            Route::patch('plans/{visit_plan}/status', [Controllers\DistributionPlanController::class, 'updateStatus'])->name('distribution.plans.update-status');
            Route::delete('plans/{visit_plan}', [Controllers\DistributionPlanController::class, 'destroy'])->name('distribution.plans.destroy');

            Route::get('promotions', [Controllers\DistributionPromotionController::class, 'index'])->name('distribution.promotions.index');
            Route::post('promotions', [Controllers\DistributionPromotionController::class, 'store'])->name('distribution.promotions.store');
            Route::put('promotions/{promotion}', [Controllers\DistributionPromotionController::class, 'update'])->name('distribution.promotions.update');
            Route::delete('promotions/{promotion}', [Controllers\DistributionPromotionController::class, 'destroy'])->name('distribution.promotions.destroy');

            Route::get('settlements', [Controllers\DistributionSettlementController::class, 'index'])->name('distribution.settlements.index');
            Route::post('settlements', [Controllers\DistributionSettlementController::class, 'store'])->name('distribution.settlements.store');
            Route::post('settlements/{daily_settlement}/submit', [Controllers\DistributionSettlementController::class, 'submit'])->name('distribution.settlements.submit');
            Route::post('settlements/{daily_settlement}/approve', [Controllers\DistributionSettlementController::class, 'approve'])->name('distribution.settlements.approve');
        });
    });

    Route::group(['prefix' => 'hr', 'as' => 'hr.'], function () {
        Route::get('employees/export', [Controllers\EmployeeController::class, 'export'])->name('employees.export');
        Route::resource('employees', Controllers\EmployeeController::class);
        Route::resource('organization-units', Controllers\OrganizationUnitController::class);
        Route::resource('org-charts', Controllers\OrgChartController::class);
        Route::resource('personnel-requests', Controllers\PersonnelRequestController::class);
        Route::patch('personnel-requests/{personnel_request}/approve', [Controllers\PersonnelRequestController::class, 'approve'])->name('personnel-requests.approve');
        Route::patch('personnel-requests/{personnel_request}/reject', [Controllers\PersonnelRequestController::class, 'reject'])->name('personnel-requests.reject');
    });

    Route::group(['prefix' => 'attendance', 'as' => 'attendance.'], function () {
        Route::resource('attendance-logs', Controllers\AttendanceLogController::class)->except(['show']);
        Route::get('attendance-logs/{attendance_log}', [Controllers\AttendanceLogController::class, 'show'])->name('attendance-logs.show');
        Route::post('attendance-logs/{attendance_log}/recalculate', [Controllers\AttendanceLogController::class, 'recalculate'])->name('attendance-logs.recalculate');
        Route::get('attendance-logs-bulk/create', [Controllers\AttendanceLogController::class, 'bulkCreate'])->name('attendance-logs.bulk-create');
        Route::get('attendance-logs-bulk/search-employee', [Controllers\AttendanceLogController::class, 'searchEmployee'])->name('attendance-logs.search-employee');
        Route::post('attendance-logs-bulk', [Controllers\AttendanceLogController::class, 'bulkStore'])->name('attendance-logs.bulk-store');
        Route::get('attendance-logs-import', [Controllers\AttendanceLogController::class, 'importForm'])->name('attendance-logs.import');
        Route::post('attendance-logs-import/preview', [Controllers\AttendanceLogController::class, 'importPreview'])->name('attendance-logs.import.preview');
        Route::post('attendance-logs-import', [Controllers\AttendanceLogController::class, 'importStore'])->name('attendance-logs.import.store');
        Route::post('monthly-attendances/{monthly_attendance}/attendance-logs/recalculate', [Controllers\AttendanceLogController::class, 'recalculateAll'])->name('attendance-logs.recalculate-all');
        Route::post('monthly-attendances/{monthly_attendance}/recalculate', [Controllers\MonthlyAttendanceController::class, 'recalculate'])->name('monthly-attendances.recalculate');
        Route::post('monthly-attendances/{monthly_attendance}/payroll', [Controllers\PayrollController::class, 'store'])->name('monthly-attendances.payroll.store');
        Route::get('monthly-attendances-bulk/create', [Controllers\MonthlyAttendanceController::class, 'bulkCreate'])->name('monthly-attendances.bulk-create');
        Route::post('monthly-attendances-bulk', [Controllers\MonthlyAttendanceController::class, 'bulkStore'])->name('monthly-attendances.bulk-store');
        Route::resource('monthly-attendances', Controllers\MonthlyAttendanceController::class);
        Route::resource('work-shifts', Controllers\WorkShiftController::class)->except(['show']);
    });

    Route::group(['prefix' => 'salary', 'as' => 'salary.'], function () {
        Route::get('payrolls/dashboard', [Controllers\PayrollController::class, 'dashboard'])->name('payrolls.dashboard');
        Route::resource('tax-slabs', Controllers\TaxSlabController::class);
        Route::resource('work-sites', Controllers\WorkSiteController::class)->except(['show']);
        Route::resource('work-site-contracts', Controllers\WorkSiteContractController::class)->except(['show']);
        Route::resource('public-holidays', Controllers\PublicHolidayController::class)->except(['show']);
        Route::resource('payroll-elements', Controllers\PayrollElementController::class)->except(['show']);
        Route::resource('salary-decrees', Controllers\SalaryDecreeController::class)->except(['show']);
        Route::patch('payrolls/{payroll}/submit-for-approval', [Controllers\PayrollController::class, 'submitForApproval'])->name('payrolls.transition.draft-to-pending-manager-approval');
        Route::patch('payrolls/{payroll}/approve', [Controllers\PayrollController::class, 'approve'])->name('payrolls.transition.pending-manager-approval-to-approved');
        Route::patch('payrolls/{payroll}/mark-paid', [Controllers\PayrollController::class, 'markPaid'])->name('payrolls.transition.approved-to-paid');
        Route::get('payrolls/{payroll}', [Controllers\PayrollController::class, 'show'])->name('payrolls.show');
        Route::get('payrolls', [Controllers\PayrollController::class, 'index'])->name('payrolls.index');
        Route::delete('payrolls/{payroll}', [Controllers\PayrollController::class, 'destroy'])->name('payrolls.destroy');
        Route::get('payroll-items/{payroll_item}/edit', [Controllers\PayrollController::class, 'editItem'])->name('payroll-items.edit');
        Route::put('payroll-items/{payroll_item}', [Controllers\PayrollController::class, 'updateItem'])->name('payroll-items.update');
    });
    Route::get('warehouse/dashboard', [Controllers\WarehouseDashboardController::class, 'index'])->name('warehouse.dashboard');

    Route::get('monthly-budgets', [Controllers\MonthlyBudgetController::class, 'index'])->name('budgets.index');
    Route::get('monthly-budgets/subjects/search/{month}/{scope?}', [Controllers\MonthlyBudgetController::class, 'searchSubjects'])->name('budgets.search-subjects');
    Route::put('monthly-budgets', [Controllers\MonthlyBudgetController::class, 'store'])->name('budgets.store');
    Route::post('monthly-budgets/rollover', [Controllers\MonthlyBudgetController::class, 'rollover'])->name('budgets.rollover');
    Route::delete('monthly-budgets/{monthlyBudget}', [Controllers\MonthlyBudgetController::class, 'destroy'])->name('budgets.destroy');

    Route::group(['prefix' => 'sync-conflicts', 'as' => 'sync-conflicts.'], function () {
        Route::get('/', [Controllers\SyncConflictController::class, 'index'])->name('index');
        Route::post('{conflict}/resolve', [Controllers\SyncConflictController::class, 'resolve'])->name('resolve');
    });
    Route::group(['prefix' => 'devices', 'as' => 'devices.'], function () {
        Route::get('/', [Controllers\DeviceController::class, 'index'])->name('index');
        Route::post('activation-codes', [Controllers\DeviceController::class, 'storeActivationCode'])->name('activation-codes.store');
        Route::post('{installation}/revoke', [Controllers\DeviceController::class, 'revoke'])->name('revoke');
    });
    Route::group(['prefix' => 'settlements', 'as' => 'settlements.'], function () {
        Route::post('cheques/{cheque}', [Controllers\SettlementController::class, 'storeForCheque'])->name('store-for-cheque');
        Route::get('{obligation}', [Controllers\SettlementController::class, 'show'])->name('show');
        Route::post('{obligation}/installments', [Controllers\SettlementController::class, 'storeInstallments'])->name('installments.store');
        Route::post('installments/{installment}/payments', [Controllers\SettlementController::class, 'recordPayment'])->name('installments.record-payment');
    });
    Route::group(['prefix' => 'bank-reconciliation', 'as' => 'bank-reconciliation.'], function () {
        Route::get('{bankAccount}', [Controllers\BankReconciliationController::class, 'index'])->name('index');
        Route::post('{bankAccount}/import', [Controllers\BankReconciliationController::class, 'import'])->name('import');
        Route::post('lines/{line}/match', [Controllers\BankReconciliationController::class, 'match'])->name('match');
        Route::post('lines/{line}/unmatch', [Controllers\BankReconciliationController::class, 'unmatch'])->name('unmatch');
    });
    Route::group(['prefix' => 'ageing', 'as' => 'ageing.'], function () {
        Route::get('receivables', [Controllers\AgeingController::class, 'receivables'])->name('receivables');
        Route::get('payables', [Controllers\AgeingController::class, 'payables'])->name('payables');
        Route::get('statement/{customer}/{type}', [Controllers\AgeingController::class, 'statement'])->name('statement');
    });
    Route::group(['prefix' => 'analytic-dimensions', 'as' => 'analytic-dimensions.'], function () {
        Route::get('/', [Controllers\AnalyticDimensionController::class, 'index'])->name('index');
        Route::post('/', [Controllers\AnalyticDimensionController::class, 'store'])->name('store');
        Route::put('{analyticDimension}', [Controllers\AnalyticDimensionController::class, 'update'])->name('update');
        Route::delete('{analyticDimension}', [Controllers\AnalyticDimensionController::class, 'destroy'])->name('destroy');
        Route::get('{analyticDimension}/report', [Controllers\AnalyticDimensionController::class, 'report'])->name('report');
        Route::put('transactions/{transaction}', [Controllers\AnalyticDimensionController::class, 'syncOnTransaction'])->name('transactions.sync');
    });
    Route::group(['prefix' => 'payment-terms', 'as' => 'payment-terms.'], function () {
        Route::get('/', [Controllers\PaymentTermController::class, 'index'])->name('index');
        Route::post('/', [Controllers\PaymentTermController::class, 'store'])->name('store');
        Route::put('{paymentTerm}', [Controllers\PaymentTermController::class, 'update'])->name('update');
        Route::post('invoices/{invoice}/apply', [Controllers\PaymentTermController::class, 'applyToInvoice'])->name('apply');
    });
    Route::group(['prefix' => 'recurring-invoice-templates', 'as' => 'recurring-invoice-templates.'], function () {
        Route::get('/', [Controllers\RecurringInvoiceTemplateController::class, 'index'])->name('index');
        Route::post('/', [Controllers\RecurringInvoiceTemplateController::class, 'store'])->name('store');
        Route::post('{recurringInvoiceTemplate}/generate', [Controllers\RecurringInvoiceTemplateController::class, 'generateNow'])->name('generate');
    });
    Route::group(['prefix' => 'currencies', 'as' => 'currencies.'], function () {
        Route::get('/', [Controllers\CurrencyController::class, 'index'])->name('index');
        Route::post('/', [Controllers\CurrencyController::class, 'store'])->name('store');
        Route::post('{currency}/rates', [Controllers\CurrencyController::class, 'storeRate'])->name('rates.store');
    });
    Route::group(['prefix' => 'fixed-assets', 'as' => 'fixed-assets.'], function () {
        Route::get('/', [Controllers\FixedAssetController::class, 'index'])->name('index');
        Route::post('/', [Controllers\FixedAssetController::class, 'store'])->name('store');
        Route::post('{fixedAsset}/depreciation', [Controllers\FixedAssetController::class, 'postDepreciation'])->name('depreciation.store');
        Route::post('{fixedAsset}/dispose', [Controllers\FixedAssetController::class, 'dispose'])->name('dispose');
    });
    Route::group(['prefix' => 'stock-reservations', 'as' => 'stock-reservations.'], function () {
        Route::get('/', [Controllers\StockReservationController::class, 'index'])->name('index');
        Route::post('/', [Controllers\StockReservationController::class, 'store'])->name('store');
        Route::post('{stockReservation}/release', [Controllers\StockReservationController::class, 'release'])->name('release');
        Route::get('availability', [Controllers\StockReservationController::class, 'availability'])->name('availability');
    });
    Route::group(['prefix' => 'invoices', 'as' => 'invoices.'], function () {
        Route::post('{invoice}/goods-receipt', [Controllers\InvoiceFulfillmentController::class, 'storeReceipt'])->name('goods-receipt.store');
        Route::post('{invoice}/delivery-note', [Controllers\InvoiceFulfillmentController::class, 'storeDelivery'])->name('delivery-note.store');
    });
    Route::group(['prefix' => 'product-lots', 'as' => 'product-lots.'], function () {
        Route::post('/', [Controllers\ProductTrackingController::class, 'storeLot'])->name('store');
    });
    Route::group(['prefix' => 'product-serial-numbers', 'as' => 'product-serial-numbers.'], function () {
        Route::post('/', [Controllers\ProductTrackingController::class, 'storeSerial'])->name('store');
        Route::post('{productSerialNumber}/sell', [Controllers\ProductTrackingController::class, 'markSerialSold'])->name('sell');
    });
    Route::group(['prefix' => 'inventory-reports', 'as' => 'inventory-reports.'], function () {
        Route::get('valuation', [Controllers\InventoryReportController::class, 'valuation'])->name('valuation');
        Route::get('slow-moving', [Controllers\InventoryReportController::class, 'slowMoving'])->name('slow-moving');
    });
    Route::group(['prefix' => 'dashboards', 'as' => 'dashboards.'], function () {
        Route::get('cash-flow', [Controllers\ReportingDashboardController::class, 'cashFlow'])->name('cash-flow');
        Route::get('cash-flow/drill-down', [Controllers\ReportingDashboardController::class, 'cashFlowDrillDown'])->name('cash-flow.drill-down');
        Route::get('cheque-risk', [Controllers\ReportingDashboardController::class, 'chequeRisk'])->name('cheque-risk');
        Route::get('profitability-by-dimension', [Controllers\ReportingDashboardController::class, 'profitabilityByDimension'])->name('profitability-by-dimension');
    });
    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('ledger', [Controllers\ReportsController::class, 'ledger'])->name('ledger');
        Route::get('journal', [Controllers\ReportsController::class, 'journal'])->name('journal');
        Route::get('sub-ledger', [Controllers\ReportsController::class, 'subLedger'])->name('sub-ledger');
        Route::get('trial-balance', [Controllers\ReportsController::class, 'trialBalance'])->name('trial-balance');
        Route::get('trial-balance.print', [Controllers\ReportsController::class, 'printTrialBalance'])->name('trial-balance.print');
        Route::get('trial-balance/export-csv', [Controllers\ReportsController::class, 'exportTrialBalanceCsv'])->name('trial-balance.export-csv');
        Route::get('documents', [Controllers\ReportsController::class, 'documents'])->name('documents');
        Route::get('result', [Controllers\ReportsController::class, 'result'])->name('result');
        Route::post('documents/export', [Controllers\DocumentController::class, 'export'])->name('documents.export');
        Route::get('cost-income', [Controllers\CostIncomeController::class, 'index'])->name('cost-income');
        Route::get('/company-overview', [Controllers\ReportsController::class, 'companyOverview'])->name('company-overview');
        // Phase U: plain-language business summary.
        Route::get('/plain', [Controllers\PlainReportController::class, 'index'])->name('plain');
        Route::group(['prefix' => 'company-overview', 'as' => 'company-overview.'], function () {
            Route::get('/cash-banks', [Controllers\ReportsController::class, 'cashAndBanksBalances'])->name('cash-banks');
            Route::get('/bank-account', [Controllers\ReportsController::class, 'bankAccount'])->name('bank-account');
            Route::post('/seed-demo-data', [Controllers\ReportsController::class, 'seedDemoData'])->name('seed-demo-data');
            Route::post('/refresh-database', [Controllers\ReportsController::class, 'refreshDatabase'])->name('refresh-database');
        });
    });

    Route::group(['prefix' => 'invoices', 'as' => 'invoices.index'], function () {
        Route::get('', [Controllers\InvoiceController::class, 'index']);
    });

    Route::prefix('customers/{customer}/comments')->as('comments.')->controller(Controllers\CommentController::class)->group(function () {
        Route::get('', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('', 'store')->name('store');
        Route::get('{comment}/edit', 'edit')->name('edit');
        Route::put('{comment}', 'update')->name('update');
        Route::delete('{comment}', 'destroy')->name('destroy');
    });

    Route::prefix('documents/{document}/files')->as('documents.files.')->controller(Controllers\DocumentFileController::class)->group(function () {
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('{documentFile}/edit', 'edit')->name('edit');
        Route::put('{documentFile}', 'update')->name('update');
        Route::delete('{documentFile}', 'destroy')->name('destroy');
        Route::get('{documentFile}/view', 'view')->name('view');
        Route::get('{documentFile}/download', 'download')->name('download');
    });
});
