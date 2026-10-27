@props(['sidebar' => false])

@php
    // Sidebar mode: plain nested collapsible groups (no floating panels, no auto-close JS).
    $topDropdownClass = $sidebar ? '' : 'app-main-menu-dropdown';
    $topDropdownContentClass = $sidebar ? '' : 'app-main-menu-panel z-50 w-max min-w-56 max-w-[calc(100vw-2rem)] overflow-x-auto';
    $scrollingTopDropdownContentClass = $sidebar ? '' : $topDropdownContentClass . ' max-h-[calc(100vh-6rem)] overflow-y-auto';
@endphp

{{-- ۱. میز کار و هوش مصنوعی (Workspace & AI) --}}
@can('home.*')
    <li><a href="/"><x-menu-icon name="home" />{{ __('Home') }}</a></li>
@endcan

@can('copilot.chat')
    <li>
        <a href="{{ route('copilot.index') }}" class="font-bold text-primary flex items-center gap-1.5">
            <x-menu-icon name="ai" class="text-primary" />
            <span>{{ __('دستیار مالی هوش مصنوعی') }}</span>
            <span class="badge badge-primary badge-xs py-0.5 px-1.5 text-[9px]">AI</span>
        </a>
    </li>
@endcan

{{-- ۲. قیمت آنلاین و تابلوی بازار (Live Market & Pricing) --}}
<li>
    <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
        <summary class="font-bold text-accent">
            <x-menu-icon name="currencies" />
            <span>{{ __('قیمت آنلاین و تابلوی بازار') }}</span>
            <span class="badge badge-accent badge-xs py-0.5 px-1 text-[9px]">LIVE</span>
        </summary>
        <ul class="{{ $topDropdownContentClass }}">
            <li>
                <a href="{{ route('market-intelligence.index', ['tab' => 'rates']) }}" class="font-bold flex items-center justify-between text-cyan-400">
                    <span>{{ __('تابلوی زنده طلا، ارز و سکه') }}</span>
                    <span class="badge badge-xs badge-accent">آنلاین</span>
                </a>
            </li>
            <li>
                <a href="{{ route('market-intelligence.index', ['tab' => 'inventory']) }}">
                    {{ __('مقایسه قیمت انبار با نرخ لحظه‌ای بازار') }}
                </a>
            </li>
            <li>
                <a href="{{ route('market-intelligence.index', ['tab' => 'guild']) }}" class="font-bold flex items-center justify-between">
                    <span>{{ __('قیمت عمده‌فروشان و هم‌صنفان (B2B)') }}</span>
                    <span class="badge badge-xs badge-accent">عمده‌فروشی</span>
                </a>
            </li>
            <li>
                <a href="{{ route('price-quotes.index') }}" class="flex items-center justify-between">
                    <span>{{ __('استعلام و سابقه قیمت‌های اعلام‌شده') }}</span>
                    <span class="badge badge-xs badge-info">قیمت‌دهی</span>
                </a>
            </li>
            <li>
                <a href="{{ route('market-intelligence.index', ['tab' => 'settings']) }}">
                    {{ __('تنظیمات منابع استعلام نرخ‌ها') }}
                </a>
            </li>
        </ul>
    </details>
</li>

{{-- ۳. بازرگانی و معاملات (Commerce & Trade: Sales, POS & Purchases) --}}
@canany(['invoices.dashboard', 'invoices.index', 'invoices.inactive', 'invoices.create', 'ancillary-costs.index', 'integrations.connections.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary class="font-bold">
                <x-menu-icon name="sales" />
                <span>{{ __('بازرگانی و معاملات') }}</span>
            </summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                @can('invoices.dashboard')
                    <li>
                        <a href="{{ route('invoices.dashboard') }}" class="font-bold text-primary">
                            {{ __('داشبورد فاکتورها و بازرگانی') }}
                        </a>
                    </li>
                @endcan

                <li>
                    <a href="{{ route('pos.terminal') }}" class="font-bold text-accent">
                        {{ __('صندوق فروشگاهی سریع (POS)') }}
                    </a>
                </li>
                <li>
                    <a href="{{ route('pc-pos.index') }}">
                        {{ __('کارتخوان‌های متصل (PC-POS)') }}
                    </a>
                </li>

                {{-- زیربخش عملیات فروش و مشتریان --}}
                <li>
                    <details open>
                        <summary class="font-semibold text-base-content/85">{{ __('عملیات فروش و مشتریان') }}</summary>
                        <ul>
                            @can('invoices.index')
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}">{{ __('فاکتورهای فروش') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_sell']) }}">{{ __('برگشت از فروش') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'void']) }}">{{ __('فاکتورهای ابطال شده') }}</a></li>
                            @endcan
                            <li>
                                <a href="{{ route('trades.index') }}" class="flex items-center justify-between">
                                    <span>{{ __('تهاتر و تبادل با همکاران') }}</span>
                                    <span class="badge badge-xs badge-info">جدید</span>
                                </a>
                            </li>
                            <li><a href="{{ route('financing.index') }}">{{ __('فروش اقساطی و تسهیلات') }}</a></li>
                            @can('integrations.connections.index')
                                <li>
                                    <a href="{{ route('integrations.connections.index') }}" class="flex items-center justify-between text-primary font-medium">
                                        <span>{{ __('اتصال به فروشگاه آنلاین (ووکامرس)') }}</span>
                                        <span class="badge badge-xs badge-primary">آنلاین</span>
                                    </a>
                                </li>
                            @endcan
                            @can('invoices.inactive')
                                <li><a href="{{ route('invoices.inactive') }}">{{ __('فعالسازی فاکتورهای غیرفعال') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>

                {{-- زیربخش عملیات خرید و تدارکات --}}
                @canany(['invoices.index', 'ancillary-costs.index'])
                    <li>
                        <details>
                            <summary class="font-semibold text-base-content/85">{{ __('عملیات خرید و تدارکات') }}</summary>
                            <ul>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}">{{ __('فاکتورهای خرید کالا') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy', 'service_buy' => '1']) }}">{{ __('خرید خدمات') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_buy']) }}">{{ __('برگشت از خرید') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_buy', 'service_buy' => '1']) }}">{{ __('برگشت خرید خدمات') }}</a></li>
                                @can('ancillary-costs.index')
                                    <li><a href="{{ route('ancillary-costs.index') }}">{{ __('هزینه‌ها و خدمات جانبی خرید (سربار)') }}</a></li>
                                @endcan
                                <li>
                                    <a href="{{ route('ai-invoices.index') }}" class="text-primary font-medium flex items-center gap-1.5">
                                        <x-menu-icon name="ai" />
                                        <span>{{ __('ورود فاکتور با هوش مصنوعی (OCR)') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </details>
                    </li>
                @endcanany
            </ul>
        </details>
    </li>
@endcanany

{{-- ۴. خزانه‌داری و نقدینگی (Treasury & Cash Management) --}}
@canany(['cheques.index', 'bank-accounts.index', 'banks.index', 'chequebooks.index', 'payment-terms.index', 'quick-payment.create', 'ageing.receivables', 'ageing.payables'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="treasury" />{{ __('خزانه‌داری و نقدینگی') }}</summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                @can('quick-payment.create')
                    <li><a href="{{ route('quick-payment.create', 'receive') }}">{{ __('دریافت وجه (سریع)') }}</a></li>
                    <li><a href="{{ route('quick-payment.create', 'pay') }}">{{ __('پرداخت وجه (سریع)') }}</a></li>
                @endcan
                <li><a href="{{ route('bank-reconciliation.landing') }}" class="font-bold flex items-center justify-between text-info"><span>{{ __('مغایرت‌گیری بانکی هوشمند') }}</span><span class="badge badge-xs badge-info">اتوماتیک</span></a></li>
                <li><a href="{{ route('reports.company-overview.cash-banks') }}">{{ __('مانده و گردش نقد و بانک‌ها') }}</a></li>
                <li><a href="{{ route('transactions.index') }}">{{ __('فهرست کلیه تراکنش‌های مالی') }}</a></li>
                @can('cheques.index')
                    <li><a href="{{ route('cheques.index') }}" class="font-bold">{{ __('مدیریت چک‌ها و صیاد') }}</a></li>
                    <li><a href="{{ route('cheques.report') }}">{{ __('گزارش تحلیلی و آماری چک‌ها') }}</a></li>
                @endcan
                @can('chequebooks.index')
                    <li><a href="{{ route('chequebooks.index') }}">{{ __('دسته‌چک‌ها') }}</a></li>
                @endcan
                @can('bank-accounts.index')
                    <li><a href="{{ route('bank-accounts.index') }}">{{ __('حساب‌های بانکی') }}</a></li>
                @endcan
                @can('banks.index')
                    <li><a href="{{ route('banks.index') }}">{{ __('بانک‌ها') }}</a></li>
                @endcan
                @can('payment-terms.index')
                    <li><a href="{{ route('payment-terms.index') }}">{{ __('شرایط پرداخت و تسویه') }}</a></li>
                @endcan
                @canany(['ageing.receivables', 'ageing.payables'])
                    <li>
                        <details>
                            <summary>{{ __('سررسید مطالبات و بدهی‌ها') }}</summary>
                            <ul>
                                @can('ageing.receivables')
                                    <li><a href="{{ route('ageing.receivables') }}">{{ __('تحلیل سررسید بدهکاران (مشتریان)') }}</a></li>
                                @endcan
                                @can('ageing.payables')
                                    <li><a href="{{ route('ageing.payables') }}">{{ __('تحلیل سررسید بستانکاران (تامین‌کنندگان)') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
            </ul>
        </details>
    </li>
@endcanany

{{-- ۵. انبارداری و کالاها (Warehouse & Inventory) --}}
@canany(['warehouse.dashboard', 'warehouses.index', 'products.index', 'product-groups.index', 'services.index', 'service-groups.index', 'stock-reservations.index', 'production.recipes.index', 'production.orders.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="warehouse" />{{ __('انبارداری و کالاها') }}</summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                @can('warehouse.dashboard')
                    <li><a href="{{ route('warehouse.dashboard') }}">{{ __('داشبورد انبار') }}</a></li>
                @endcan
                @can('products.index')
                    <li><a href="{{ route('products.index') }}">{{ __('کالاها') }}</a></li>
                @endcan
                @can('product-groups.index')
                    <li><a href="{{ route('product-groups.index') }}">{{ __('گروه‌های کالا') }}</a></li>
                @endcan
                @can('services.index')
                    <li><a href="{{ route('services.index') }}">{{ __('خدمات') }}</a></li>
                @endcan
                @can('service-groups.index')
                    <li><a href="{{ route('service-groups.index') }}">{{ __('گروه‌های خدمت') }}</a></li>
                @endcan
                @can('warehouses.index')
                    <li><a href="{{ route('warehouses.index') }}">{{ __('انبارها') }}</a></li>
                    <li><a href="{{ route('warehouses.transfer') }}">{{ __('انتقال و جابجایی بین انبارها') }}</a></li>
                    <li><a href="{{ route('warehouses.transfer-history') }}">{{ __('تاریخچه حواله‌های انتقال') }}</a></li>
                @endcan
                @can('stock-reservations.index')
                    <li><a href="{{ route('stock-reservations.index') }}">{{ __('رزرو کالا و تعهدات سفارش') }}</a></li>
                @endcan
                <li><a href="{{ route('inventory-reports.valuation') }}">{{ __('ارزش‌گذاری ریالی انبار') }}</a></li>
                <li><a href="{{ route('inventory-reports.slow-moving') }}">{{ __('کالاهای راکد و کم‌گردش') }}</a></li>
                @canany(['production.orders.index', 'production.recipes.index'])
                    <li>
                        <details>
                            <summary>{{ __('تولید و بهای تمام‌شده') }}</summary>
                            <ul>
                                @can('production.orders.index')
                                    <li><a href="{{ route('production.orders.index') }}">{{ __('سفارشات تولید') }}</a></li>
                                @endcan
                                @can('production.recipes.index')
                                    <li><a href="{{ route('production.recipes.index') }}">{{ __('فرمولاسیون و Recipes') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
            </ul>
        </details>
    </li>
@endcanany

{{-- ۶. حسابداری مالی و دفاتر (Financial Accounting & Ledgers) --}}
@canany(['documents.index', 'documents.create', 'fixed-assets.index', 'currencies.index', 'recurring-invoice-templates.index', 'analytic-dimensions.index', 'subjects.index', 'reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="accounting" />{{ __('حسابداری مالی') }}</summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                @can('documents.create')
                    <li><a href="{{ route('documents.create') }}" class="font-bold text-primary">{{ __('ثبت سند حسابداری جدید') }}</a></li>
                @endcan
                @can('documents.index')
                    <li><a href="{{ route('documents.index') }}">{{ __('لیست اسناد حسابداری') }}</a></li>
                @endcan
                @can('subjects.index')
                    <li><a href="{{ route('subjects.index') }}">{{ __('کدینگ حساب‌ها (سرفصل‌ها)') }}</a></li>
                @endcan
                @canany(['reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance'])
                    <li>
                        <details>
                            <summary>{{ __('دفاتر مالی و ترازنامه') }}</summary>
                            <ul>
                                @can('reports.journal')
                                    <li><a href="{{ route('reports.journal') }}">{{ __('دفتر روزنامه') }}</a></li>
                                @endcan
                                @can('reports.ledger')
                                    <li><a href="{{ route('reports.ledger') }}">{{ __('دفتر کل') }}</a></li>
                                @endcan
                                @can('reports.sub-ledger')
                                    <li><a href="{{ route('reports.sub-ledger') }}">{{ __('دفتر معین') }}</a></li>
                                @endcan
                                @can('reports.trial-balance')
                                    <li><a href="{{ route('reports.trial-balance') }}" class="font-bold">{{ __('تراز آزمایشی') }}</a></li>
                                @endcan
                                @can('reports.documents')
                                    <li><a href="{{ route('reports.documents') }}">{{ __('خلاصه اسناد') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
                @can('documents.sort-numbers')
                    <li><a href="{{ route('documents.sort-numbers') }}">{{ __('مرتب‌سازی شماره اسناد') }}</a></li>
                @endcan
                @can('fixed-assets.index')
                    <li><a href="{{ route('fixed-assets.index') }}">{{ __('اموال و دارایی‌های ثابت') }}</a></li>
                @endcan
                @can('recurring-invoice-templates.index')
                    <li><a href="{{ route('recurring-invoice-templates.index') }}">{{ __('الگوهای فاکتور دوره‌ای') }}</a></li>
                @endcan
                @can('currencies.index')
                    <li><a href="{{ route('currencies.index') }}">{{ __('ارزها و تسعیر ارز') }}</a></li>
                @endcan
                @can('analytic-dimensions.index')
                    <li><a href="{{ route('analytic-dimensions.index') }}">{{ __('ابعاد و مراکز تحلیلی (پروژه‌ها و مراکز هزینه)') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

{{-- ۷. سامانه مودیان و مالیات (Tax & Moadian Center) --}}
@canany(['tax-center.index', 'tax-readiness.index', 'seasonal-tax.index', 'invoices.moadian-histories.index', 'invoices.moadian-queue.index', 'tax-alerts.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="tax" />{{ __('Tax') }}</summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                @can('tax-center.index')
                    <li><a href="{{ route('tax-center.index') }}" class="font-bold text-primary">{{ __('پیشخوان و داشبورد مالیاتی') }}</a></li>
                @endcan
                @can('invoices.moadian-queue.index')
                    <li><a href="{{ route('invoices.moadian-queue.index') }}" class="font-medium flex items-center justify-between"><span>{{ __('کارپوشه سامانه مودیان') }}</span><span class="badge badge-warning badge-xs">کارپوشه</span></a></li>
                @endcan
                @can('invoices.moadian-histories.index')
                    <li><a href="{{ route('invoices.moadian-histories.index') }}">{{ __('تاریخچه ارسال‌های مودیان') }}</a></li>
                @endcan
                @can('tax-center.prepare')
                    <li><a href="{{ route('tax-center.prepare') }}">{{ __('آماده‌سازی اظهارنامه مالیاتی') }}</a></li>
                @endcan
                @can('tax-center.calendar')
                    <li><a href="{{ route('tax-center.calendar') }}">{{ __('تقویم تکالیف مالیاتی') }}</a></li>
                @endcan
                @can('tax-readiness.index')
                    <li><a href="{{ route('tax-readiness.index') }}">{{ __('آمادگی مالیاتی و سلامت پرونده') }}</a></li>
                @endcan
                @can('seasonal-tax.index')
                    <li><a href="{{ route('seasonal-tax.index') }}">{{ __('گزارش فصلی ماده ۱۶۹') }}</a></li>
                @endcan
                <li><a href="{{ route('tax-center.audit-pack') }}">{{ __('دانلود بسته ممیزی مالیاتی') }}</a></li>
                @can('tax-alerts.index')
                    <li><a href="{{ route('tax-alerts.index') }}">{{ __('هشدارهای ریسک ممیزی مالیاتی') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

{{-- ۸. طرف‌حساب‌ها و ارتباط با مشتریان (CRM & Customers) --}}
@canany(['crm.dashboard', 'customers.index', 'customers.create', 'customer-groups.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="crm" />{{ __('مشتریان و CRM') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('crm.dashboard')
                    <li><a href="{{ route('crm.dashboard') }}">{{ __('داشبورد CRM') }}</a></li>
                @endcan
                @can('customers.create')
                    <li><a href="{{ route('customers.create') }}" class="font-medium text-primary">{{ __('تعریف مشتری جدید') }}</a></li>
                @endcan
                @can('customers.index')
                    <li><a href="{{ route('customers.index') }}">{{ __('لیست مشتریان') }}</a></li>
                @endcan
                @can('customer-groups.index')
                    <li><a href="{{ route('customer-groups.index') }}">{{ __('گروه‌بندی مشتریان') }}</a></li>
                @endcan
                <li><a href="{{ route('loyalty.index') }}">{{ __('باشگاه مشتریان و کیف‌پول اعتباری') }}</a></li>
                <li><a href="{{ route('telephony.index') }}">{{ __('کالرآیدی و تلفن سانترال') }}</a></li>
                <li><a href="{{ route('inquiries.index') }}">{{ __('استعلام آنلاین شاهکار، شبا و کارت') }}</a></li>
                <li><a href="{{ route('sms.index') }}">{{ __('ارسال پیامک هوشمند') }}</a></li>
            </ul>
        </details>
    </li>
@endcanany

{{-- ۹. منابع انسانی و پرسنل (HR, Payroll & Employee Portal) [ادغام پرتال پرسنلی] --}}
@canany(['salary.payrolls.dashboard', 'salary.payrolls.index', 'hr.employees.index', 'hr.personnel-requests.index',
    'attendance.attendance-logs.index', 'attendance.monthly-attendances.index', 'hr.org-charts.index', 'employee-portal.dashboard'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="hr" />{{ __('منابع انسانی و پرسنل') }}</summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                @can('salary.payrolls.dashboard')
                    <li><a href="{{ route('salary.payrolls.dashboard') }}">{{ __('داشبورد حقوق و دستمزد') }}</a></li>
                @endcan
                @can('hr.employees.index')
                    <li><a href="{{ route('hr.employees.index') }}" class="font-bold">{{ __('پرسنل و کارمندان') }}</a></li>
                @endcan
                @can('salary.payrolls.index')
                    <li><a href="{{ route('salary.payrolls.index') }}">{{ __('فیش‌ها و لیست حقوق') }}</a></li>
                @endcan
                @can('hr.personnel-requests.index')
                    <li><a href="{{ route('hr.personnel-requests.index') }}">{{ __('درخواست‌های پرسنلی (مرخصی و مساعده)') }}</a></li>
                @endcan

                {{-- کارتابل پرسنلی من (ادغام شده از پرتال من قدیم) --}}
                @can('employee-portal.dashboard')
                    <li>
                        <details>
                            <summary class="font-semibold text-primary">{{ __('کارتابل پرسنلی من') }}</summary>
                            <ul>
                                <li><a href="{{ route('employee-portal.dashboard') }}">{{ __('داشبورد من') }}</a></li>
                                <li><a href="{{ route('employee-portal.attendance-logs') }}">{{ __('ترددهای من') }}</a></li>
                                <li><a href="{{ route('employee-portal.monthly-attendances') }}">{{ __('کارکرد ماهانه من') }}</a></li>
                                <li><a href="{{ route('employee-portal.payrolls') }}">{{ __('فیش‌های حقوقی من') }}</a></li>
                                <li><a href="{{ route('employee-portal.personnel-requests.index') }}">{{ __('درخواست‌های مرخصی و مساعده من') }}</a></li>
                                <li><a href="{{ route('employee-portal.employee.show') }}">{{ __('اطلاعات فردی و استخدامی من') }}</a></li>
                            </ul>
                        </details>
                    </li>
                @endcan

                <li>
                    <details>
                        <summary>{{ __('حضور، غیاب و شیفت‌ها') }}</summary>
                        <ul>
                            @can('attendance.attendance-logs.index')
                                <li><a href="{{ route('attendance.attendance-logs.index') }}">{{ __('ترددهای ثبت شده') }}</a></li>
                            @endcan
                            @can('attendance.monthly-attendances.index')
                                <li><a href="{{ route('attendance.monthly-attendances.index') }}">{{ __('کارکرد ماهانه پرسنل') }}</a></li>
                            @endcan
                            <li><a href="{{ route('attendance.devices.index') }}">{{ __('دستگاه‌های ثبت ساعت (سخت‌افزار)') }}</a></li>
                            @can('attendance.work-shifts.index')
                                <li><a href="{{ route('attendance.work-shifts.index') }}">{{ __('شیفت‌های کاری') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
                <li>
                    <details>
                        <summary>{{ __('احکام و پایه‌های حقوقی') }}</summary>
                        <ul>
                            @can('salary.salary-decrees.index')
                                <li><a href="{{ route('salary.salary-decrees.index') }}">{{ __('احکام کارگزینی و حقوقی') }}</a></li>
                            @endcan
                            @can('salary.payroll-elements.index')
                                <li><a href="{{ route('salary.payroll-elements.index') }}">{{ __('عوامل و اقلام حقوقی') }}</a></li>
                            @endcan
                            @can('salary.tax-slabs.index')
                                <li><a href="{{ route('salary.tax-slabs.index') }}">{{ __('پله‌های مالیات سالانه') }}</a></li>
                            @endcan
                            @can('salary.work-sites.index')
                                <li><a href="{{ route('salary.work-sites.index') }}">{{ __('کارگاه‌ها و شعب بیمه') }}</a></li>
                            @endcan
                            @can('salary.work-site-contracts.index')
                                <li><a href="{{ route('salary.work-site-contracts.index') }}">{{ __('پیمان‌ها و قراردادهای کارگاهی') }}</a></li>
                            @endcan
                            @can('salary.public-holidays.index')
                                <li><a href="{{ route('salary.public-holidays.index') }}">{{ __('تقویم تعطیلات رسمی') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
                @canany(['hr.org-charts.index', 'hr.organization-units.index'])
                    <li>
                        <details>
                            <summary>{{ __('ساختار سازمانی') }}</summary>
                            <ul>
                                @can('hr.org-charts.index')
                                    <li><a href="{{ route('hr.org-charts.index') }}">{{ __('چارت سازمانی تصویری') }}</a></li>
                                @endcan
                                @can('hr.organization-units.index')
                                    <li><a href="{{ route('hr.organization-units.index') }}">{{ __('واحدهای سازمانی') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
            </ul>
        </details>
    </li>
@endcanany

{{-- ۱۰. پخش مویرگی و ویزیتوری (Capillary distribution) --}}
@canany(['distribution.index', 'distribution.routes.index', 'distribution.visitors.index', 'distribution.plans.index', 'distribution.promotions.index', 'distribution.settlements.index', 'field-operations-analytics.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="field" />{{ __('پخش مویرگی و ویزیتوری') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('distribution.index')
                    <li><a href="{{ route('distribution.index') }}">{{ __('داشبورد و شاخص‌های پخش') }}</a></li>
                    <li><a href="{{ route('mobile.index') }}" target="_blank" class="text-cyan-400 font-bold flex items-center justify-between"><span>📱 {{ __('اپلیکیشن همراه ویزیتور و انبار') }}</span><span class="badge badge-xs badge-info">PWA</span></a></li>
                @endcan
                @can('distribution.routes.index')
                    <li><a href="{{ route('distribution.routes.index') }}">{{ __('مسیرها و مناطق توزیع') }}</a></li>
                @endcan
                @can('distribution.visitors.index')
                    <li><a href="{{ route('distribution.visitors.index') }}">{{ __('ویزیتورها و بازاریابان') }}</a></li>
                @endcan
                @can('distribution.plans.index')
                    <li><a href="{{ route('distribution.plans.index') }}">{{ __('برنامه‌ریزی تور ویزیت') }}</a></li>
                @endcan
                @can('distribution.promotions.index')
                    <li><a href="{{ route('distribution.promotions.index') }}">{{ __('آیین‌نامه‌ها و تخفیفات وزنی/تعدادی') }}</a></li>
                @endcan
                @can('distribution.settlements.index')
                    <li><a href="{{ route('distribution.settlements.index') }}">{{ __('تسویه روزانه ماموریت‌های پخش') }}</a></li>
                @endcan
                @can('field-operations-analytics.index')
                    <li><a href="{{ route('field-operations-analytics.index') }}">{{ __('تحلیل پیشرفته عملیات میدانی') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

{{-- ۱۱. گزارش‌ها و هوش تجاری (BI & Reports) --}}
@canany(['reports.company-overview', 'reports.cost-income', 'dashboards.cash-flow', 'dashboards.cheque-risk', 'dashboards.profitability-by-dimension', 'inventory-reports.valuation', 'inventory-reports.slow-moving', 'budgets.index', 'reports.plain'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary><x-menu-icon name="reports" />{{ __('گزارش‌ها و هوش تجاری') }}</summary>
            <ul class="{{ $scrollingTopDropdownContentClass }}">
                <li><a href="{{ route('reports.plain') }}" class="font-bold text-primary">{{ __('وضعیت کسب‌وکار به زبان ساده (مدیریتی)') }}</a></li>
                @can('reports.company-overview')
                    <li><a href="{{ route('reports.company-overview') }}" class="font-bold">{{ __('گزارش جامع وضعیت شرکت') }}</a></li>
                @endcan
                @can('reports.cost-income')
                    <li><a href="{{ route('reports.cost-income') }}">{{ __('داشبورد درآمد و هزینه‌ها') }}</a></li>
                @endcan
                @can('dashboards.cash-flow')
                    <li><a href="{{ route('dashboards.cash-flow') }}">{{ __('جریان وجوه نقد (نقدینگی)') }}</a></li>
                @endcan
                @can('dashboards.cheque-risk')
                    <li><a href="{{ route('dashboards.cheque-risk') }}">{{ __('سنجش ریسک چک‌های دریافتی') }}</a></li>
                @endcan
                @can('dashboards.profitability-by-dimension')
                    <li><a href="{{ route('dashboards.profitability-by-dimension') }}">{{ __('سودآوری به تفکیک مراکز هزینه و پروژه‌ها') }}</a></li>
                @endcan
                @can('budgets.index')
                    <li><a href="{{ route('budgets.index') }}">{{ __('بودجه‌بندی ماهانه و انحراف بودجه') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

{{-- ۱۲. مدیریت سیستم، قالب‌ها و یکپارچه‌سازی (System & Settings) --}}
<li>
    <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
        <summary><x-menu-icon name="settings" />{{ __('مدیریت و تنظیمات') }}</summary>
        <ul class="{{ $scrollingTopDropdownContentClass }}">
            <li><a href="{{ route('settings.appearance') }}" class="font-bold text-primary flex items-center justify-between"><span>{{ __('تنظیمات ظاهری و پوسته سیستم') }}</span><span class="badge badge-primary badge-xs">Google Flow</span></a></li>
            @can('companies.index')
                <li><a href="{{ route('companies.index') }}" class="font-bold">{{ __('شرکت‌ها و سال مالی') }}</a></li>
            @endcan
            @can('users.index')
                <li><a href="{{ route('users.index') }}">{{ __('کاربران و پرسنل سیستم') }}</a></li>
            @endcan
            <li><a href="{{ route('account.edit') }}">{{ __('پروفایل و حساب کاربری من') }}</a></li>
            @can('industry-profiles.index')
                <li><a href="{{ route('industry-profiles.index') }}" class="flex items-center justify-between"><span>{{ __('تنظیمات اختصاصی صنف (طلا، موبایل، یدکی و...)') }}</span><span class="badge badge-accent badge-xs">اصناف</span></a></li>
            @endcan
            <li><a href="{{ route('templates.index') }}">{{ __('طراح قالب‌های چاپ فاکتور') }}</a></li>
            <li><a href="{{ route('migration.index') }}" class="flex items-center justify-between text-info font-medium"><span>{{ __('مهاجرت از رقبا (هلو، سپیدار و...)') }}</span><span class="badge badge-info badge-xs">هوشمند</span></a></li>
            @can('configs.index')
                <li><a href="{{ route('license.index') }}" class="flex items-center justify-between"><span>{{ __('مدیریت لایسنس و اشتراک') }}</span><span class="badge badge-primary badge-xs">Pro</span></a></li>
            @endcan
            <li><a href="{{ route('api-tokens.index') }}">{{ __('کلیدها و توکن‌های API') }}</a></li>
            <li><a href="{{ route('devices.index') }}">{{ __('دستگاه‌ها و صندوق‌های فعال شعب') }}</a></li>
            <li><a href="{{ route('sync-conflicts.index') }}">{{ __('مدیریت شعب و تعارضات آفلاین') }}</a></li>
            <li><a href="{{ route('pricing') }}" class="flex items-center justify-between"><span>{{ __('تعرفه‌ها و بسته امکانات') }}</span><span class="badge badge-xs badge-outline">پلن‌ها</span></a></li>
            @canany(['access-super-admin-panel', 'activity-logs.index'])
                <li><a href="{{ route('management.activity-logs.index') }}">{{ __('لاگ فعالیت‌ها و ردپای حسابرسی') }}</a></li>
            @endcanany
            @can('integrations.queue.index')
                <li><a href="{{ route('integrations.queue.index') }}">{{ __('صف بازبینی سفارشات آنلاین') }}</a></li>
            @endcan
            @can('workflow-governance.edit')
                <li><a href="{{ route('workflow-governance.edit') }}">{{ __('حاکمیت فرآیندها (Workflow Governance)') }}</a></li>
            @endcan
            @can('backups.create')
                <li>
                    <details>
                        <summary>{{ __('پشتیبان‌گیری و بازیابی') }}</summary>
                        <ul>
                            <li><a href="{{ route('backups.create') }}">{{ __('ایجاد نسخه پشتیبان') }}</a></li>
                            <li><a href="{{ route('backups.upload') }}">{{ __('بارگذاری و بازگردانی فایل') }}</a></li>
                        </ul>
                    </details>
                </li>
            @endcan
            @can('configs.index')
                <li><a href="{{ route('configs.index') }}">{{ __('تنظیمات عمومی سامانه') }}</a></li>
            @endcan
            <li><a href="{{ route('guide.index') }}" class="font-bold text-primary flex items-center justify-between"><span>{{ __('راهنمای جامع و مصور نرم‌افزار') }}</span><span class="badge badge-primary badge-xs">آموزش</span></a></li>
            <li><a href="{{ route('about') }}">{{ __('درباره سامانه دخل') }}</a></li>
        </ul>
    </details>
</li>
