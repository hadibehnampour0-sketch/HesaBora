<x-app-layout :title="__('Analytic dimensions')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Analytic dimensions') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Tag accounting lines with a branch, project, cost center or sales channel to see profit for each one separately.') }}</p>
        </div>

        @can('analytic-dimensions.store')
            <form method="POST" action="{{ route('analytic-dimensions.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('New dimension') }}</div>
                <select name="type" class="select select-bordered" required>
                    @foreach (\App\Enums\AnalyticDimensionType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                <input type="text" name="code" class="input input-bordered" placeholder="{{ __('Code') }}" maxlength="50" required>
                <input type="text" name="name" class="input input-bordered" placeholder="{{ __('Name') }}" maxlength="150" required>
                <button class="btn btn-primary btn-sm">{{ __('Add') }}</button>
            </form>
        @endcan

        @can('dashboards.profitability-by-dimension')
            <a href="{{ route('dashboards.profitability-by-dimension') }}" class="btn btn-sm btn-outline">{{ __('Profitability by dimension') }}</a>
        @endcan

        @forelse (\App\Enums\AnalyticDimensionType::cases() as $case)
            @continue(! $dimensions->has($case->value))
            <div class="today-panel">
                <div class="today-panel-title">{{ $case->label() }}</div>
                <ul class="mt-2 divide-y divide-base-200 text-sm">
                    @foreach ($dimensions[$case->value] as $dimension)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-1">
                            <span>
                                <span class="badge badge-outline badge-sm">{{ $dimension->code }}</span> {{ $dimension->name }}
                                @unless ($dimension->is_active)<span class="badge badge-ghost badge-sm">{{ __('Inactive') }}</span>@endunless
                            </span>
                            <span class="flex gap-1">
                                @can('analytic-dimensions.report')
                                    <a class="btn btn-ghost btn-xs" href="{{ route('analytic-dimensions.report', $dimension) }}">{{ __('Report') }}</a>
                                @endcan
                                @can('analytic-dimensions.update')
                                    <form method="POST" action="{{ route('analytic-dimensions.update', $dimension) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $dimension->is_active ? 0 : 1 }}">
                                        <button class="btn btn-ghost btn-xs">{{ $dimension->is_active ? __('Deactivate') : __('Activate') }}</button>
                                    </form>
                                @endcan
                                @can('analytic-dimensions.destroy')
                                    <form method="POST" action="{{ route('analytic-dimensions.destroy', $dimension) }}" onsubmit="return confirm('{{ __('Delete this dimension?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-xs text-error">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
        @endforelse

        @if ($dimensions->isEmpty())
            <div class="alert text-sm">{{ __('No dimensions yet. Add the first one above.') }}</div>
        @endif
    </div>
</x-app-layout>
