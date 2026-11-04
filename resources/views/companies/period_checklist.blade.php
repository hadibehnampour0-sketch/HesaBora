<x-platform-layout title="{{ __('چک‌لیست آمادگی و وضعیت دوره مالی') }} – {{ $company->name }} {{ $company->fiscal_year }}">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/30">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ __('چک‌لیست جامع آمادگی و بستن دوره مالی') }}
                    </h2>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="badge badge-lg badge-primary gap-1">{{ $company->fiscal_year }}</span>
                        <span class="badge badge-lg badge-neutral">{{ $company->name }}</span>
                        <span class="badge badge-lg {{ $company->period_status?->value === 'closed' ? 'badge-error' : ($company->period_status?->value === 'locked' ? 'badge-warning' : 'badge-success') }}">
                            {{ $company->period_status?->label() ?? __('Open') }}
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('companies.closing-wizard', $company) }}" class="btn btn-warning btn-sm gap-2">
                        {{ __('جادوگر بستن سال مالی') }}
                    </a>
                    <a href="{{ route('companies.index') }}" class="btn btn-ghost btn-sm">
                        {{ __('بازگشت') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body space-y-6">
            <x-show-message-bags />

            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="stat bg-base-200/60 rounded-xl p-4 border border-base-300">
                    <div class="stat-title text-xs font-semibold">{{ __('امکان بستن دوره') }}</div>
                    <div class="stat-value text-lg {{ $checklist['can_close'] ? 'text-success' : 'text-error' }}">
                        {{ $checklist['can_close'] ? __('آماده بستن قطعی') : __('نیازمند رفع مغایرت') }}
                    </div>
                </div>
                <div class="stat bg-base-200/60 rounded-xl p-4 border border-base-300">
                    <div class="stat-title text-xs font-semibold">{{ __('خطاهای مسدودکننده (Blockers)') }}</div>
                    <div class="stat-value text-lg {{ $checklist['blockers_count'] > 0 ? 'text-error' : 'text-success' }}">
                        {{ localizeNumber($checklist['blockers_count']) }} {{ __('مورد') }}
                    </div>
                </div>
                <div class="stat bg-base-200/60 rounded-xl p-4 border border-base-300">
                    <div class="stat-title text-xs font-semibold">{{ __('هشدارهای نظارتی') }}</div>
                    <div class="stat-value text-lg text-warning">
                        {{ localizeNumber($checklist['warnings_count']) }} {{ __('مورد') }}
                    </div>
                </div>
                <div class="stat bg-base-200/60 rounded-xl p-4 border border-base-300">
                    <div class="stat-title text-xs font-semibold">{{ __('امتیاز آمادگی مالیاتی') }}</div>
                    <div class="stat-value text-lg text-primary">
                        {{ localizeNumber($checklist['tax_readiness_score'] ?? 100) }} / ۱۰۰
                    </div>
                </div>
            </div>

            {{-- Checklist Items Table --}}
            <div class="overflow-x-auto border border-base-200 rounded-xl">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr class="bg-base-200/50">
                            <th>{{ __('شاخص کنترلی') }}</th>
                            <th>{{ __('وضعیت') }}</th>
                            <th>{{ __('تعداد / مقدار') }}</th>
                            <th>{{ __('شرح وضعیت و توصیه') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($checklist['items'] as $key => $item)
                            <tr>
                                <td class="font-bold text-sm">{{ $item['title'] }}</td>
                                <td>
                                    @if ($item['status'] === 'pass')
                                        <span class="badge badge-success text-xs gap-1">
                                            ✓ {{ __('تایید') }}
                                        </span>
                                    @elseif ($item['status'] === 'blocker')
                                        <span class="badge badge-error text-xs gap-1">
                                            ✕ {{ __('مسدودکننده') }}
                                        </span>
                                    @else
                                        <span class="badge badge-warning text-xs gap-1">
                                            ! {{ __('هشدار') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="font-mono text-sm">{{ localizeNumber($item['count'] ?? 0) }}</td>
                                <td class="text-xs text-base-content/80">{{ $item['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t">
                <div class="text-xs text-base-content/60">
                    {{ __('پس از برطرف کردن موارد مسدودکننده، می‌توانید وضعیت دوره مالی را قفل یا بسته قطعی کنید.') }}
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('companies.period-status', $company) }}">
                        @csrf
                        <input type="hidden" name="status" value="locked">
                        <button type="submit" class="btn btn-warning btn-sm" {{ ! $checklist['can_close'] ? 'disabled' : '' }}>
                            {{ __('قفل قطعی دوره مالی (Locked)') }}
                        </button>
                    </form>
                    <a href="{{ route('companies.closing-wizard', $company) }}" class="btn btn-primary btn-sm">
                        {{ __('ورود به عملیات بستن حساب‌ها') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-platform-layout>
