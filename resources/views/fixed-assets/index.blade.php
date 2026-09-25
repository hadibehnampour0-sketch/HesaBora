<x-app-layout :title="__('Fixed assets')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Fixed assets') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Register equipment, vehicles and property, post monthly depreciation, and record disposal. Every posting creates a normal accounting document.') }}</p>
        </div>

        @can('fixed-assets.store')
            <form method="POST" action="{{ route('fixed-assets.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('Register an asset') }}</div>
                <input type="text" name="code" class="input input-bordered" placeholder="{{ __('Asset code') }}" maxlength="50" required>
                <input type="text" name="name" class="input input-bordered sm:col-span-2" placeholder="{{ __('Asset name') }}" maxlength="150" required>
                <input type="text" name="acquisition_date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}" required>
                <input type="number" step="any" min="0" name="acquisition_cost" class="input input-bordered" placeholder="{{ __('Acquisition cost') }}" required>
                <input type="number" step="any" min="0" name="salvage_value" class="input input-bordered" placeholder="{{ __('Salvage value') }}">
                <input type="number" min="1" name="useful_life_months" class="input input-bordered" placeholder="{{ __('Useful life (months)') }}" required>
                <span></span>
                @foreach ([
                    'asset_subject_id' => __('Asset account'),
                    'depreciation_expense_subject_id' => __('Depreciation expense account'),
                    'accumulated_depreciation_subject_id' => __('Accumulated depreciation account'),
                ] as $field => $label)
                    <select name="{{ $field }}" class="select select-bordered" required>
                        <option value="">{{ $label }}</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->formattedCode() }} — {{ $subject->name }}</option>
                        @endforeach
                    </select>
                @endforeach
                <div class="flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Register') }}</button></div>
            </form>
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Acquired') }}</th><th>{{ __('Cost') }}</th>
                        <th>{{ __('Accumulated depreciation') }}</th><th>{{ __('Book value') }}</th><th>{{ __('Status') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        @php
                            $accumulated = $service->accumulatedDepreciation($asset);
                            $active = $asset->status === \App\Enums\FixedAssetStatus::ACTIVE;
                        @endphp
                        <tr>
                            <td>{{ $asset->code }}</td>
                            <td>{{ $asset->name }}</td>
                            <td>{{ formatDate($asset->acquisition_date) }}</td>
                            <td>{{ number_format((float) $asset->acquisition_cost) }}</td>
                            <td>{{ number_format($accumulated) }}</td>
                            <td class="font-semibold">{{ number_format((float) $asset->acquisition_cost - $accumulated) }}</td>
                            <td><span class="badge badge-sm {{ $active ? 'badge-success' : 'badge-ghost' }}">{{ $asset->status->label() }}</span></td>
                            <td class="text-end">
                                @if ($active)
                                    <div class="flex flex-wrap justify-end gap-1">
                                        @can('fixed-assets.depreciation.store')
                                            <form method="POST" action="{{ route('fixed-assets.depreciation.store', $asset) }}" class="flex gap-1">
                                                @csrf
                                                <input type="text" name="period_date" data-jdp autocomplete="off" readonly class="input input-bordered input-xs w-28" value="{{ convertToJalali(now(), true) }}">
                                                <button class="btn btn-xs btn-outline">{{ __('Post depreciation') }}</button>
                                            </form>
                                        @endcan
                                        @can('fixed-assets.dispose')
                                            <form method="POST" action="{{ route('fixed-assets.dispose', $asset) }}" class="flex gap-1" onsubmit="return confirm('{{ __('Dispose of this asset?') }}')">
                                                @csrf
                                                <input type="text" name="disposed_at" data-jdp autocomplete="off" readonly class="input input-bordered input-xs w-28" value="{{ convertToJalali(now(), true) }}">
                                                <button class="btn btn-xs btn-ghost text-error">{{ __('Dispose') }}</button>
                                            </form>
                                        @endcan
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-sm text-base-content/60">{{ __('No fixed assets yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endpush
</x-app-layout>
