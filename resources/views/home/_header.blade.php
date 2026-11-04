@php
    $homeProfilesDesc = [
        'platform' => __('Move between management and the active company workspace.'),
        'accounting' => __('Continue with documents, accounts, and financial reports.'),
        'sales' => __('Continue with customers, sales invoices, and CRM.'),
        'inventory' => __('Continue with stock, products, and warehouse operations.'),
        'services' => __('Continue with services and service groups.'),
        'crm' => __('Continue with customers and CRM activities.'),
        'operations' => __('Work across sales and warehouse tasks from one focused page.'),
        'employee' => __('Open your attendance, payroll, and personnel requests.'),
        'business' => __('Choose a task and continue in the dedicated workspace.'),
    ];
    $homeProfileDesc = $homeProfilesDesc[$homeVariant] ?? $homeProfilesDesc['business'];
@endphp

<section data-home-variant="{{ $homeVariant }}" class="relative isolate overflow-hidden rounded-3xl border border-emerald-500/20 bg-gradient-to-r from-base-100 via-base-100 to-emerald-500/5 p-4 shadow-sm sm:p-5">
    <div aria-hidden="true" class="absolute -end-20 -top-24 size-64 rounded-full blur-3xl bg-emerald-500/10"></div>
    <div aria-hidden="true" class="absolute -bottom-24 start-1/3 size-48 rounded-full bg-cyan-500/10 blur-3xl"></div>

    <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3.5">
            <div class="relative size-12 shrink-0 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center font-black text-xl shadow-md shadow-emerald-500/20">
                <span>د</span>
                <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full bg-emerald-400 ring-2 ring-base-100 animate-pulse"></span>
            </div>

            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg sm:text-xl font-black tracking-tight text-base-content">
                        {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}
                    </h1>
                    <span class="badge badge-sm badge-primary badge-outline font-semibold">
                        {{ optional(auth()->user()->activeCompany)->name ?? 'محیط کاری دخل' }}
                    </span>
                    <span class="badge badge-sm badge-ghost text-xs font-mono">
                        سال مالی {{ session('fiscal_year', '۱۴۰۵') }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-base-content/65 max-w-2xl leading-5">
                    {{ $homeProfileDesc }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2 self-start md:self-auto">
            @if($homeVariant === 'platform' || auth()->user()->can('access-super-admin-panel'))
                <a href="{{ route('management.dashboard') }}" class="btn btn-xs sm:btn-sm btn-ghost border border-base-300 rounded-xl text-xs gap-1.5 hover:border-primary">
                    <span>⚙️</span>
                    <span>{{ __('پنل مدیریت پلتفرم') }}</span>
                </a>
            @endif

            <aside class="flex items-center gap-2 rounded-2xl border border-base-300 bg-base-100/90 px-3 py-1.5 shadow-xs">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-bold text-base-content font-mono">{{ formatDate(now(), 'l، j F Y') }}</span>
            </aside>
        </div>
    </div>
</section>
