<x-app-layout :title="__('Prepare my tax filing')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Prepare my tax filing') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('No accounting knowledge needed: pick a period, fix anything marked with a cross, then download the pack to hand to the tax office.') }}</p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="form-control"><span class="label-text mb-1 text-xs">{{ __('Fiscal Year') }}</span>
                <input type="number" name="jalali_year" value="{{ $jalaliYear }}" class="input input-bordered input-sm w-28"></label>
            <div role="tablist" class="tabs tabs-boxed">
                @foreach ([1 => __('Spring'), 2 => __('Summer'), 3 => __('Autumn'), 4 => __('Winter')] as $q => $label)
                    <a role="tab" href="{{ route('tax-center.prepare', ['jalali_year' => $jalaliYear, 'quarter' => $q]) }}" class="tab {{ $quarter === $q ? 'tab-active' : '' }}">{{ __('Quarter') }} {{ $q }} ({{ $label }})</a>
                @endforeach
            </div>
            <button type="submit" class="btn btn-sm btn-outline">{{ __('Show') }}</button>
        </form>

        <div class="today-panel">
            <div class="flex items-center justify-between">
                <div class="today-panel-title">{{ $preparation['report']['quarter_label'] }} {{ $preparation['report']['jalali_year'] }}</div>
                <span class="badge {{ $preparation['is_ready'] ? 'badge-success' : 'badge-warning' }} badge-lg">
                    {{ $preparation['is_ready'] ? __('Ready for the tax office') : __('Not ready yet') }}
                </span>
            </div>

            <ul class="mt-4 space-y-2">
                @foreach ($preparation['steps'] as $step)
                    <li class="today-row items-start {{ $step['done'] ? '' : 'border-warning/40' }}">
                        <span class="flex items-start gap-2">
                            <span class="badge badge-sm mt-0.5 {{ $step['done'] ? 'badge-success' : 'badge-warning' }}">{{ $step['done'] ? '✓' : '!' }}</span>
                            <span>
                                <span class="font-medium">{{ $step['label'] }}</span>
                                @if ($step['detail'])
                                    <span class="block text-xs text-base-content/60">{{ $step['detail'] }}</span>
                                @endif
                            </span>
                        </span>
                        @unless ($step['done'])
                            @can($step['fix_route'])
                                <a href="{{ route($step['fix_route']) }}" class="btn btn-xs btn-outline">{{ __('Fix') }}</a>
                            @endcan
                        @endunless
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="today-panel">
            <div class="today-panel-title">{{ __('Filing steps') }}</div>
            <ol class="mt-3 list-decimal space-y-1 ps-5 text-sm">
                <li>{{ __('Review the checklist above until everything is marked with a check.') }}</li>
                <li>{{ __('Download the audit pack for this period.') }}</li>
                <li>{{ __('Log into the tax office portal and file using the figures in the pack.') }}</li>
                <li>{{ __('Keep the pack for your own records.') }}</li>
            </ol>
            <p class="mt-3 text-xs text-base-content/60">{{ __('Dakhl prepares the figures and the pack; it does not submit your filing to the tax office for you.') }}</p>

            @can('tax-center.audit-pack')
                <a href="{{ route('tax-center.audit-pack', ['jalali_year' => $jalaliYear, 'quarter' => $quarter]) }}" class="btn btn-sm btn-primary mt-3">{{ __('Download audit pack') }}</a>
            @endcan
        </div>
    </div>
</x-app-layout>
