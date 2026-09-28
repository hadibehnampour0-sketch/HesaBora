@props(['sidebar' => false])

@php
    // Sidebar mode: plain nested collapsible groups (no floating panels, no auto-close JS).
    $topDropdownClass = $sidebar ? '' : 'app-main-menu-dropdown';
    $topDropdownContentClass = $sidebar ? '' : 'app-main-menu-panel z-50 w-max min-w-56 max-w-[calc(100vw-2rem)] overflow-x-auto';
    $scrollingTopDropdownContentClass = $sidebar ? '' : $topDropdownContentClass . ' max-h-[calc(100vh-6rem)] overflow-y-auto';
@endphp

@can('home.*')
    <li><a href="/"><x-menu-icon name="home" />{{ __('Home') }}</a></li>
@endcan

@can('employee-portal.dashboard')
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="portal" />{{ __('My Portal') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                <li><a href="{{ route('employee-portal.dashboard') }}">{{ __('Dashboard') }}</a></li>
                <li><a href="{{ route('employee-portal.attendance-logs') }}">{{ __('My Attendance') }}</a></li>
                <li><a href="{{ route('employee-portal.monthly-attendances') }}">{{ __('Monthly Attendance') }}</a></li>
                <li><a href="{{ route('employee-portal.payrolls') }}">{{ __('My Payrolls') }}</a></li>
                <li><a href="{{ route('employee-portal.personnel-requests.index') }}">{{ __('My Requests') }}</a></li>
                <li><a href="{{ route('employee-portal.employee.show') }}">{{ __('My Information') }}</a></li>
            </ul>
        </details>
    </li>
@endcan
@canany(['invoices.dashboard', 'invoices.index', 'invoices.inactive', 'customers.create', 'ancillary-costs.index',
    'invoices.moadian-histories.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="invoices" />{{ __('Invoices') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('invoices.dashboard')
                    <li><a href="{{ route('invoices.dashboard') }}">{{ __('Invoice Dashboard') }}</a></li>
                @endcan
                @can('invoices.index')
                    <li>
                        <details>
                            <summary>{{ __('Sales') }}</summary>
                            <ul>
                                @can('invoices.index')
                                    <li><a
                                            href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}">{{ __('Sell List') }}</a>
                                    </li>
                                    <li><a
                                            href="{{ route('invoices.index', ['invoice_type' => 'return_sell']) }}">{{ __('Return Sell List') }}</a>
                                    </li>
                                    <li><a
                                            href="{{ route('invoices.index', ['invoice_type' => 'void']) }}">{{ __('Voided Sell') }}</a>
                                    </li>
                                    <li><a
                                            href="{{ route('invoices.moadian-histories.index') }}">{{ __('Moadian Histories') }}</a>
                                    </li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcan
                @canany(['invoices.index', 'ancillary-costs.index'])
                    <li>
                        <details>
                            <summary>{{ __('Purchases') }}</summary>
                            <ul>
                                <li><a
                                        href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}">{{ __('Buy List') }}</a>
                                </li>
                                <li><a
                                        href="{{ route('invoices.index', ['invoice_type' => 'buy', 'service_buy' => '1']) }}">{{ __('Buy Service') }}</a>
                                </li>
                                <li><a
                                        href="{{ route('invoices.index', ['invoice_type' => 'return_buy']) }}">{{ __('Return Buy List') }}</a>
                                </li>
                                <li><a
                                        href="{{ route('invoices.index', ['invoice_type' => 'return_buy', 'service_buy' => '1']) }}">{{ __('Service Buy Return') }}</a>
                                </li>
                                @can('ancillary-costs.index')
                                    <li><a href="{{ route('ancillary-costs.index') }}">{{ __('Ancillary Cost List') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
                @can('invoices.inactive')
                    <li><a href="{{ route('invoices.inactive') }}">{{ __('Activate Confirmed Invoices') }}</a></li>
                @endcan
                @can('customers.create')
                    <li><a href="{{ route('customers.create') }}">{{ __('Add Customer') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany
@canany(['documents.index', 'documents.create', 'fixed-assets.index', 'currencies.index', 'payment-terms.index', 'recurring-invoice-templates.index', 'analytic-dimensions.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="accounting" />{{ __('Accounting') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('documents.create')
                    <li><a href="{{ route('documents.create') }}">{{ __('Create Document') }}</a></li>
                @endcan
                @can('documents.index')
                    <li><a href="{{ route('documents.index') }}">{{ __('Document List') }}</a></li>
                @endcan
                @can('cheques.index')
                    <li><a href="{{ route('cheques.index') }}">{{ __('Cheque Management') }}</a></li>
                @endcan
                @can('fixed-assets.index')
                    <li><a href="{{ route('fixed-assets.index') }}">{{ __('Fixed assets') }}</a></li>
                @endcan
                @can('recurring-invoice-templates.index')
                    <li><a href="{{ route('recurring-invoice-templates.index') }}">{{ __('Recurring invoices') }}</a></li>
                @endcan
                @can('payment-terms.index')
                    <li><a href="{{ route('payment-terms.index') }}">{{ __('Payment terms') }}</a></li>
                @endcan
                @can('currencies.index')
                    <li><a href="{{ route('currencies.index') }}">{{ __('Currencies') }}</a></li>
                @endcan
                @can('analytic-dimensions.index')
                    <li><a href="{{ route('analytic-dimensions.index') }}">{{ __('Analytic dimensions') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany
{{-- Tax is its own top-level area (not a Reports sub-item): a differentiator that must stay complete and easy to find. --}}
@canany(['tax-center.index', 'tax-readiness.index', 'seasonal-tax.index', 'invoices.moadian-histories.index', 'invoices.moadian-queue.index', 'tax-alerts.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="tax" />{{ __('Tax') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('tax-center.index')
                    <li><a href="{{ route('tax-center.index') }}">{{ __('Tax Center') }}</a></li>
                @endcan
                @can('tax-center.prepare')
                    <li><a href="{{ route('tax-center.prepare') }}">{{ __('Prepare my tax filing') }}</a></li>
                @endcan
                @can('tax-center.calendar')
                    <li><a href="{{ route('tax-center.calendar') }}">{{ __('Filing calendar') }}</a></li>
                @endcan
                @can('tax-readiness.index')
                    <li><a href="{{ route('tax-readiness.index') }}">{{ __('Tax Readiness') }}</a></li>
                @endcan
                @can('seasonal-tax.index')
                    <li><a href="{{ route('seasonal-tax.index') }}">{{ __('Seasonal Tax Report') }}</a></li>
                @endcan
                @can('invoices.moadian-histories.index')
                    <li><a href="{{ route('invoices.moadian-histories.index') }}">{{ __('Moadian Histories') }}</a></li>
                @endcan
                @can('invoices.moadian-queue.index')
                    <li><a href="{{ route('invoices.moadian-queue.index') }}">{{ __('Moadian work queue') }}</a></li>
                @endcan
                @can('tax-alerts.index')
                    <li><a href="{{ route('tax-alerts.index') }}">{{ __('Tax alerts') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany
@canany(['reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance',
    'reports.cost-income', 'budgets.index', 'reports.company-overview', 'ageing.receivables', 'ageing.payables', 'dashboards.cash-flow', 'dashboards.cheque-risk', 'dashboards.profitability-by-dimension', 'inventory-reports.valuation', 'inventory-reports.slow-moving'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="reports" />{{ __('Reports') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('reports.company-overview')
                    <li><a href="{{ route('reports.company-overview') }}">{{ __('Company overview') }}</a></li>
                @endcan
                @can('reports.cost-income')
                    <li><a href="{{ route('reports.cost-income') }}">{{ __('Cost and Income Dashboard') }}</a></li>
                @endcan
                @can('dashboards.cash-flow')
                    <li><a href="{{ route('dashboards.cash-flow') }}">{{ __('Cash flow') }}</a></li>
                @endcan
                @can('dashboards.cheque-risk')
                    <li><a href="{{ route('dashboards.cheque-risk') }}">{{ __('Cheque risk') }}</a></li>
                @endcan
                @can('dashboards.profitability-by-dimension')
                    <li><a href="{{ route('dashboards.profitability-by-dimension') }}">{{ __('Profitability by dimension') }}</a></li>
                @endcan
                @can('inventory-reports.valuation')
                    <li><a href="{{ route('inventory-reports.valuation') }}">{{ __('Inventory valuation') }}</a></li>
                @endcan
                @can('inventory-reports.slow-moving')
                    <li><a href="{{ route('inventory-reports.slow-moving') }}">{{ __('Slow-moving stock') }}</a></li>
                @endcan
                @can('budgets.index')
                    <li><a href="{{ route('budgets.index') }}">{{ __('Monthly Income and Expense Workbench') }}</a></li>
                @endcan
                @canany(['reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger',
                    'reports.trial-balance'])
                    <li>
                        <details>
                            <summary>{{ __('Accounting') }}</summary>
                            <ul>
                                @can('reports.documents')
                                    <li><a href="{{ route('reports.documents') }}">{{ __('Documents Report') }}</a></li>
                                @endcan
                                @can('reports.journal')
                                    <li><a href="{{ route('reports.journal') }}">{{ __('Journal') }}</a></li>
                                @endcan
                                @can('reports.ledger')
                                    <li><a href="{{ route('reports.ledger') }}">{{ __('Ledger') }}</a></li>
                                @endcan
                                @can('reports.sub-ledger')
                                    <li><a href="{{ route('reports.sub-ledger') }}">{{ __('Sub Ledger') }}</a></li>
                                @endcan
                                @can('reports.trial-balance')
                                    <li><a href="{{ route('reports.trial-balance') }}">{{ __('Trial Balance') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
                @canany(['ageing.receivables', 'ageing.payables'])
                    <li>
                        <details>
                            <summary>{{ __('AR / AP Ageing') }}</summary>
                            <ul>
                                @can('ageing.receivables')
                                    <li><a href="{{ route('ageing.receivables') }}">{{ __('Accounts Receivable Ageing') }}</a></li>
                                @endcan
                                @can('ageing.payables')
                                    <li><a href="{{ route('ageing.payables') }}">{{ __('Accounts Payable Ageing') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
            </ul>
        </details>
    </li>
@endcanany

@canany(['warehouse.dashboard', 'warehouses.index', 'products.index', 'product-groups.index', 'services.index', 'service-groups.index', 'stock-reservations.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="warehouse" />{{ __('Warehouse') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('warehouse.dashboard')
                    <li><a href="{{ route('warehouse.dashboard') }}">{{ __('Warehouse Dashboard') }}</a></li>
                @endcan
                @can('warehouses.index')
                    <li><a href="{{ route('warehouses.index') }}">{{ __('Warehouses') }}</a></li>
                @endcan
                @can('stock-reservations.index')
                    <li><a href="{{ route('stock-reservations.index') }}">{{ __('Stock reservations') }}</a></li>
                @endcan
                @can('products.index')
                    <li><a href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
                @endcan
                @can('product-groups.index')
                    <li><a href="{{ route('product-groups.index') }}">{{ __('Product Groups') }}</a></li>
                @endcan
                @can('services.index')
                    <li><a href="{{ route('services.index') }}">{{ __('Services') }}</a></li>
                @endcan
                @can('service-groups.index')
                    <li><a href="{{ route('service-groups.index') }}">{{ __('Service Groups') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['salary.payrolls.dashboard', 'salary.payrolls.index', 'hr.employees.index', 'hr.personnel-requests.index',
    'attendance.attendance-logs.index', 'attendance.monthly-attendances.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="hr" />{{ __('HR') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('salary.payrolls.dashboard')
                    <li><a href="{{ route('salary.payrolls.dashboard') }}">{{ __('Payroll Dashboard') }}</a></li>
                @endcan
                @can('salary.payrolls.index')
                    <li><a href="{{ route('salary.payrolls.index') }}">{{ __('Payrolls') }}</a></li>
                @endcan
                @can('hr.employees.index')
                    <li><a href="{{ route('hr.employees.index') }}">{{ __('Employees') }}</a></li>
                @endcan
                @can('hr.personnel-requests.index')
                    <li><a href="{{ route('hr.personnel-requests.index') }}">{{ __('Personnel Requests') }}</a></li>
                @endcan
                @can('attendance.attendance-logs.index')
                    <li><a href="{{ route('attendance.attendance-logs.index') }}">{{ __('Attendance Logs') }}</a></li>
                @endcan
                @can('attendance.monthly-attendances.index')
                    <li><a href="{{ route('attendance.monthly-attendances.index') }}">{{ __('Monthly Attendances') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

{{-- Capillary distribution (پخش مویرگی) is its own top-level area: routes, visitors + van warehouses, visit plans, daily settlement, analytics. --}}
@canany(['distribution.index', 'distribution.routes.index', 'distribution.visitors.index', 'distribution.plans.index', 'distribution.promotions.index', 'distribution.settlements.index', 'field-operations-analytics.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="field" />{{ __('Capillary distribution') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('distribution.index')
                    <li><a href="{{ route('distribution.index') }}">{{ __('Overview') }}</a></li>
                @endcan
                @can('distribution.routes.index')
                    <li><a href="{{ route('distribution.routes.index') }}">{{ __('Distribution routes') }}</a></li>
                @endcan
                @can('distribution.visitors.index')
                    <li><a href="{{ route('distribution.visitors.index') }}">{{ __('Visitors') }}</a></li>
                @endcan
                @can('distribution.plans.index')
                    <li><a href="{{ route('distribution.plans.index') }}">{{ __('Visit plans') }}</a></li>
                @endcan
                @can('distribution.promotions.index')
                    <li><a href="{{ route('distribution.promotions.index') }}">{{ __('Promotions') }}</a></li>
                @endcan
                @can('distribution.settlements.index')
                    <li><a href="{{ route('distribution.settlements.index') }}">{{ __('Daily settlements') }}</a></li>
                @endcan
                @can('field-operations-analytics.index')
                    <li><a href="{{ route('field-operations-analytics.index') }}">{{ __('Field Operations Analytics') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@can('industry-profiles.index')
    <li><a href="{{ route('industry-profiles.index') }}"><x-menu-icon name="industry" />{{ __('Industry Profiles') }}</a></li>
@endcan

@canany(['crm.dashboard', 'customers.index', 'customer-groups.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="crm" />{{ __('CRM') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('crm.dashboard')
                    <li><a href="{{ route('crm.dashboard') }}">{{ __('CRM Dashboard') }}</a></li>
                @endcan
                @can('customers.index')
                    <li><a href="{{ route('customers.index') }}">{{ __('Customers') }}</a></li>
                @endcan
                @can('customer-groups.index')
                    <li><a href="{{ route('customer-groups.index') }}">{{ __('Customer Groups') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

<li>
    <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
        <summary><x-menu-icon name="management" />{{ __('Management') }}</summary>
        <ul class="{{ $scrollingTopDropdownContentClass }}">

            {{-- HR, Attendance & Salary --}}
            @canany(['hr.org-charts.index', 'hr.organization-units.index', 'attendance.work-shifts.index',
                'salary.payrolls.index', 'salary.tax-slabs.index', 'salary.work-sites.index',
                'salary.work-site-contracts.index', 'salary.public-holidays.index', 'salary.payroll-elements.index',
                'salary.salary-decrees.index'])
                <li>
                    <details>
                        <summary>{{ __('HR & Organization') }}</summary>
                        <ul>
                            @can('hr.org-charts.index')
                                <li><a href="{{ route('hr.org-charts.index') }}">{{ __('Organization Chart') }}</a></li>
                            @endcan
                            @can('hr.organization-units.index')
                                <li><a href="{{ route('hr.organization-units.index') }}">{{ __('Organization Units') }}</a>
                                </li>
                            @endcan
                            @can('attendance.work-shifts.index')
                                <li><a href="{{ route('attendance.work-shifts.index') }}">{{ __('Work Shifts') }}</a></li>
                            @endcan
                            @can('salary.payrolls.index')
                                <li><a href="{{ route('salary.payrolls.index') }}">{{ __('Payrolls') }}</a></li>
                            @endcan
                            @can('salary.tax-slabs.index')
                                <li><a href="{{ route('salary.tax-slabs.index') }}">{{ __('Yearly Tax Slabs') }}</a></li>
                            @endcan
                            @can('salary.work-sites.index')
                                <li><a href="{{ route('salary.work-sites.index') }}">{{ __('Work Sites') }}</a></li>
                            @endcan
                            @can('salary.work-site-contracts.index')
                                <li><a
                                        href="{{ route('salary.work-site-contracts.index') }}">{{ __('Work Site Contracts') }}</a>
                                </li>
                            @endcan
                            @can('salary.public-holidays.index')
                                <li><a href="{{ route('salary.public-holidays.index') }}">{{ __('Public Holidays') }}</a>
                                </li>
                            @endcan
                            @can('salary.payroll-elements.index')
                                <li><a href="{{ route('salary.payroll-elements.index') }}">{{ __('Payroll Elements') }}</a>
                                </li>
                            @endcan
                            @can('salary.salary-decrees.index')
                                <li><a href="{{ route('salary.salary-decrees.index') }}">{{ __('Salary Decrees') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
            @endcanany

            {{-- Finance --}}
            @canany(['bank-accounts.index', 'banks.index', 'chequebooks.index', 'subjects.index',
                'documents.sort-numbers'])
                <li>
                    <details>
                        <summary>{{ __('Finance') }}</summary>
                        <ul>
                            @can('subjects.index')
                                <li><a href="{{ route('subjects.index') }}">{{ __('Subjects') }}</a></li>
                            @endcan
                            @can('bank-accounts.index')
                                <li><a href="{{ route('bank-accounts.index') }}">{{ __('Bank Accounts') }}</a></li>
                            @endcan
                            @can('banks.index')
                                <li><a href="{{ route('banks.index') }}">{{ __('Banks') }}</a></li>
                            @endcan
                            @can('chequebooks.index')
                                <li><a href="{{ route('chequebooks.index') }}">{{ __('Chequebooks') }}</a></li>
                            @endcan
                            @can('documents.sort-numbers')
                                <li><a href="{{ route('documents.sort-numbers') }}">{{ __('Sort Documents Number') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </details>
                </li>
            @endcanany

            {{-- System --}}
            @canany(['companies.index', 'users.index', 'configs.index', 'backups.create', 'workflow-governance.edit', 'integrations.connections.index', 'integrations.queue.index'])
                <li>
                    <details>
                        <summary>{{ __('System') }}</summary>
                        <ul>
                            @can('companies.index')
                                <li><a href="{{ route('companies.index') }}">{{ __('Companies') }}</a></li>
                            @endcan
                            @can('integrations.connections.index')
                                <li><a href="{{ route('integrations.connections.index') }}">{{ __('Integrations') }}</a></li>
                            @endcan
                            @can('integrations.queue.index')
                                <li><a href="{{ route('integrations.queue.index') }}">{{ __('Integration review queue') }}</a></li>
                            @endcan
                            @can('users.index')
                                <li><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
                            @endcan
                            @can('configs.index')
                                <li><a href="{{ route('configs.index') }}">{{ __('Configs') }}</a></li>
                            @endcan
                            @can('workflow-governance.edit')
                                <li><a href="{{ route('workflow-governance.edit') }}">{{ __('Workflow Governance') }}</a></li>
                            @endcan
                            @can('backups.create')
                                <li><a href="{{ route('backups.create') }}">{{ __('Backup') }}</a></li>
                                <li><a href="{{ route('backups.upload') }}">{{ __('Upload Backup') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
            @endcanany

            <li><a href="{{ route('about') }}">{{ __('about.about') }}</a></li>
        </ul>
    </details>
</li>
