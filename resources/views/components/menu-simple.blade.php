{{-- Simple-mode navigation: a handful of plain-language groups over the SAME routes and permissions as the full menu. --}}
@can('home.*')
    <li><a href="/"><x-menu-icon name="home" />{{ __('Home') }}</a></li>
@endcan

@can('copilot.chat')
    <li>
        <a href="{{ route('copilot.index') }}" class="font-bold text-primary flex items-center gap-1.5">
            <span class="text-base">✨</span>
            {{ __('دستیار مالی هوش مصنوعی') }}
            <span class="badge badge-primary badge-xs py-1 px-1.5 text-[9px]">AI</span>
        </a>
    </li>
@endcan

<li>
    <a href="{{ route('market-intelligence.index') }}" class="flex items-center justify-between text-accent font-medium">
        <div class="flex items-center gap-1.5">
            <x-menu-icon name="currencies" />
            <span>{{ __('قیمت آنلاین و تابلوی بازار') }}</span>
        </div>
        <span class="badge badge-accent badge-xs py-0.5 px-1 text-[9px]">LIVE</span>
    </a>
</li>

@canany(['invoices.create', 'invoices.index'])
    <li>
        <details open>
            <summary><x-menu-icon name="sales" />{{ __('Selling') }}</summary>
            <ul>
                @can('invoices.create')
                    <li><a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}">{{ __('New sale') }}</a></li>
                    <li><a href="{{ route('pos.terminal') }}">{{ __('صندوق فروشگاهی سریع (POS)') }}</a></li>
                    <li><a href="{{ route('pc-pos.index') }}">{{ __('کارتخوان‌های متصل (PC-POS)') }}</a></li>
                    <li><a href="{{ route('trades.index') }}">{{ __('تهاتر و تبادل با همکاران') }}</a></li>
                    <li><a href="{{ route('price-quotes.index') }}">{{ __('استعلام و سابقه قیمت‌ها') }}</a></li>
                    <li><a href="{{ route('market-intelligence.index', ['tab' => 'guild']) }}" class="flex items-center justify-between"><span>{{ __('قیمت عمده‌فروشان (B2B)') }}</span><span class="badge badge-xs badge-accent">عمده</span></a></li>
                    @can('integrations.connections.index')
                        <li><a href="{{ route('integrations.connections.index') }}">{{ __('اتصال به فروشگاه آنلاین (ووکامرس)') }}</a></li>
                    @endcan
                @endcan
                @can('invoices.index')
                    <li><a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}">{{ __('My sales') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['invoices.create', 'invoices.index', 'ancillary-costs.index'])
    <li>
        <details>
            <summary><x-menu-icon name="buy" />{{ __('Buying') }}</summary>
            <ul>
                @can('invoices.create')
                    <li><a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}">{{ __('New purchase') }}</a></li>
                @endcan
                @can('invoices.index')
                    <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}">{{ __('My purchases') }}</a></li>
                @endcan
                @can('ancillary-costs.index')
                    <li><a href="{{ route('ancillary-costs.index') }}">{{ __('Expenses') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['quick-payment.create', 'ageing.receivables', 'ageing.payables', 'cheques.index'])
    <li>
        <details>
            <summary><x-menu-icon name="money" />{{ __('Money in and out') }}</summary>
            <ul>
                @can('quick-payment.create')
                    <li><a href="{{ route('quick-payment.create', 'receive') }}">{{ __('Collect money') }}</a></li>
                    <li><a href="{{ route('quick-payment.create', 'pay') }}">{{ __('Pay suppliers') }}</a></li>
                @endcan
                @can('ageing.receivables')
                    <li><a href="{{ route('ageing.receivables') }}">{{ __('Who owes me') }}</a></li>
                @endcan
                @can('ageing.payables')
                    <li><a href="{{ route('ageing.payables') }}">{{ __('Whom I owe') }}</a></li>
                @endcan
                @can('cheques.index')
                    <li><a href="{{ route('cheques.index') }}">{{ __('Cheques') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['customers.index', 'customers.create'])
    <li>
        <details>
            <summary><x-menu-icon name="customers" />{{ __('Customers') }}</summary>
            <ul>
                @can('customers.index')
                    <li><a href="{{ route('customers.index') }}">{{ __('Customer list') }}</a></li>
                @endcan
                @can('customers.create')
                    <li><a href="{{ route('customers.create') }}">{{ __('New customer') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['products.index', 'products.create', 'warehouse.dashboard'])
    <li>
        <details>
            <summary><x-menu-icon name="products" />{{ __('Products') }}</summary>
            <ul>
                @can('products.index')
                    <li><a href="{{ route('products.index') }}">{{ __('Product list') }}</a></li>
                @endcan
                @can('products.create')
                    <li><a href="{{ route('products.create') }}">{{ __('New product') }}</a></li>
                @endcan
                @can('warehouse.dashboard')
                    <li><a href="{{ route('warehouse.dashboard') }}">{{ __('Stock overview') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['reports.plain', 'reports.company-overview', 'reports.cost-income'])
    <li>
        <details>
            <summary><x-menu-icon name="reports" />{{ __('Reports') }}</summary>
            <ul>
                @can('reports.plain')
                    <li><a href="{{ route('reports.plain') }}">{{ __('How is my business doing?') }}</a></li>
                @endcan
                @can('reports.company-overview')
                    <li><a href="{{ route('reports.company-overview') }}">{{ __('Business overview') }}</a></li>
                @endcan
                @can('reports.cost-income')
                    <li><a href="{{ route('reports.cost-income') }}">{{ __('Income and costs') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@can('industry-profiles.index')
    <li><a href="{{ route('industry-profiles.index') }}"><x-menu-icon name="industry" />{{ __('Industry Profiles') }}</a></li>
@endcan

<li>
    <details>
        <summary><x-menu-icon name="settings" />{{ __('تنظیمات و پوسته') }}</summary>
        <ul>
            <li><a href="{{ route('settings.appearance') }}" class="font-bold text-primary">{{ __('تنظیمات ظاهری و پوسته') }}</a></li>
            <li><a href="{{ route('account.edit') }}">{{ __('پروفایل و حساب کاربری') }}</a></li>
            <li><a href="{{ route('about') }}">{{ __('درباره دخل') }}</a></li>
        </ul>
    </details>
</li>
