<x-app-layout :title="__('Cash flow')">
    <x-show-message-bags />

    @php $max = max(1, (float) collect($report['periods'])->flatMap(fn ($p) => [$p['inflow'], $p['outflow']])->max()); @endphp

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Cash flow') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Money that entered and left your cash and bank accounts, by period.') }}</p>
        </div>

        <form method="GET" class="today-panel flex flex-wrap items-end gap-3">
            <label class="form-control"><span class="label-text mb-1 text-xs">{{ __('From') }}</span>
                <input type="text" name="from" data-jdp autocomplete="off" readonly class="input input-bordered input-sm" value="{{ convertToJalali($from, true) }}"></label>
            <label class="form-control"><span class="label-text mb-1 text-xs">{{ __('To') }}</span>
                <input type="text" name="to" data-jdp autocomplete="off" readonly class="input input-bordered input-sm" value="{{ convertToJalali($to, true) }}"></label>
            <label class="form-control"><span class="label-text mb-1 text-xs">{{ __('Group by') }}</span>
                <select name="group_by" class="select select-bordered select-sm">
                    <option value="month" @selected($groupBy === 'month')>{{ __('Month') }}</option>
                    <option value="week" @selected($groupBy === 'week')>{{ __('Week') }}</option>
                    <option value="day" @selected($groupBy === 'day')>{{ __('Day') }}</option>
                </select></label>
            <button class="btn btn-sm btn-primary">{{ __('Show') }}</button>
        </form>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Money in') }}</span><span class="today-kpi-value text-success">{{ number_format($report['total_inflow']) }}</span></div>
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Money out') }}</span><span class="today-kpi-value text-error">{{ number_format($report['total_outflow']) }}</span></div>
            <div class="today-kpi"><span class="today-kpi-label">{{ __('Net') }}</span><span class="today-kpi-value">{{ number_format($report['net']) }}</span></div>
        </div>

        <div class="today-panel space-y-3">
            @forelse ($report['periods'] as $period)
                <div>
                    <div class="mb-1 flex justify-between text-xs"><b>{{ $period['period'] }}</b><span>{{ __('Net') }}: {{ number_format($period['net']) }}</span></div>
                    <div class="h-2 rounded bg-base-200"><div class="h-2 rounded bg-success" style="width: {{ round($period['inflow'] / $max * 100) }}%"></div></div>
                    <div class="mt-1 h-2 rounded bg-base-200"><div class="h-2 rounded bg-error" style="width: {{ round($period['outflow'] / $max * 100) }}%"></div></div>
                    <div class="mt-1 flex justify-between text-xs text-base-content/60"><span>{{ number_format($period['inflow']) }}</span><span>{{ number_format($period['outflow']) }}</span></div>
                </div>
            @empty
                <div class="text-sm text-base-content/60">{{ __('No cash movements in this range.') }}</div>
            @endforelse
        </div>
    </div>

    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endpush
</x-app-layout>
