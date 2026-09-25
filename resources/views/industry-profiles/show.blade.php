<x-app-layout :title="\App\Services\Industry\IndustryProfileRegistry::text($profile['title'])">
    <x-show-message-bags />

    @php
        $t = fn ($value) => \App\Services\Industry\IndustryProfileRegistry::text($value);

        $statusMeta = [
            'verified' => ['label' => __('Verified'), 'badge' => 'badge-success'],
            'assumed' => ['label' => __('Assumed'), 'badge' => 'badge-info'],
            'needs_confirmation' => ['label' => __('Needs confirmation'), 'badge' => 'badge-warning'],
        ];
        $actionLabels = [
            'update' => __('Changed'),
            'reset' => __('Reset to default'),
            'confirm' => __('Confirmed'),
            'activate' => __('Activated'),
            'deactivate' => __('Deactivated'),
        ];
    @endphp

    <div class="space-y-6">
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <a href="{{ route('industry-profiles.index') }}" class="text-xs opacity-60 hover:underline">← {{ __('Industry Profiles') }}</a>
                        <h1 class="card-title mt-1">{{ $t($profile['title']) }}</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($pending['needs_confirmation'] > 0)
                            <span class="badge badge-warning">{{ __(':count need confirmation', ['count' => $pending['needs_confirmation']]) }}</span>
                        @endif
                        @if ($pending['assumed'] > 0)
                            <span class="badge badge-info">{{ __(':count assumed', ['count' => $pending['assumed']]) }}</span>
                        @endif
                        @can('industry-profiles.toggle')
                            <form method="POST" action="{{ route('industry-profiles.toggle', $profile['key']) }}">
                                @csrf
                                <button class="btn btn-sm {{ $active ? 'btn-outline' : 'btn-primary' }}" type="submit">
                                    {{ $active ? __('Deactivate') : __('Activate for my company') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
                <p class="text-sm text-base-content/70">{{ $t($profile['description'] ?? '') }}</p>
                <div class="alert alert-warning text-sm mt-2">
                    {{ __('Defaults marked “Assumed” or “Needs confirmation” are analyst estimates, not verified practice. Nothing here posts to the ledger or changes invoices; values feed the informational calculators only.') }}
                </div>
            </div>
        </div>

        {{-- Settings --}}
        @can('industry-profiles.update')
            <form method="POST" action="{{ route('industry-profiles.update', $profile['key']) }}" class="space-y-6">
                @csrf
                @method('PUT')
        @endcan

        @foreach ($profile['sections'] as $section)
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <span class="card-title text-base">{{ $t($section['title']) }}</span>

                    <div class="divide-y divide-base-200">
                        @foreach ($section['fields'] as $field)
                            @php
                                $key = $field['key'];
                                $state = $effective[$key];
                                $meta = $statusMeta[$field['status']];
                                $current = old('settings.'.$key, $state['is_overridden'] ? $state['value'] : '');
                                $unit = $field['unit'] ?? '';
                            @endphp
                            <div class="py-3 grid grid-cols-1 lg:grid-cols-3 gap-3 items-start">
                                <div class="lg:col-span-1">
                                    <div class="font-medium">{{ $t($field['title']) }}</div>
                                    @if (! empty($field['help']))
                                        <div class="text-xs text-base-content/60 mt-1">{{ $t($field['help']) }}</div>
                                    @endif
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        <span class="badge badge-sm {{ $meta['badge'] }}">{{ $meta['label'] }}</span>
                                        @if ($state['confirmed_at'])
                                            <span class="badge badge-sm badge-success badge-outline">{{ __('Confirmed by your company') }}</span>
                                        @endif
                                        @if ($state['is_overridden'])
                                            <span class="badge badge-sm badge-outline">{{ __('Customised') }}</span>
                                        @endif
                                    </div>
                                    @if (! empty($field['source']))
                                        <div class="text-xs opacity-50 mt-1">{{ __('Source') }}: {{ $field['source'] }}</div>
                                    @endif
                                </div>

                                <div class="lg:col-span-1">
                                    @php
                                        $defaultShown = $state['default'];
                                        if ($field['type'] === 'boolean') { $defaultShown = $state['default'] ? __('Yes') : __('No'); }
                                        if ($field['type'] === 'select') {
                                            foreach ($field['options'] as $option) {
                                                if ((string) $option['value'] === (string) $state['default']) { $defaultShown = $t($option['label']); }
                                            }
                                        }
                                    @endphp
                                    @can('industry-profiles.update')
                                        @if ($field['type'] === 'boolean')
                                            <select name="settings[{{ $key }}]" class="select select-bordered w-full">
                                                <option value="">{{ __('Default') }} ({{ $defaultShown }})</option>
                                                <option value="1" @selected((string) $current === '1')>{{ __('Yes') }}</option>
                                                <option value="0" @selected((string) $current === '0' || ($state['is_overridden'] && $state['value'] === false))>{{ __('No') }}</option>
                                            </select>
                                        @elseif ($field['type'] === 'select')
                                            <select name="settings[{{ $key }}]" class="select select-bordered w-full">
                                                <option value="">{{ __('Default') }} ({{ $defaultShown }})</option>
                                                @foreach ($field['options'] as $option)
                                                    <option value="{{ $option['value'] }}" @selected((string) $current === (string) $option['value'])>{{ $t($option['label']) }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div class="join w-full">
                                                <input type="{{ $field['type'] === 'text' ? 'text' : 'number' }}"
                                                    @if ($field['type'] !== 'text') step="any" @endif
                                                    name="settings[{{ $key }}]" value="{{ $current }}"
                                                    placeholder="{{ __('Default') }}: {{ $defaultShown }}"
                                                    class="input input-bordered join-item w-full" />
                                                @if ($unit !== '')
                                                    <span class="join-item btn btn-disabled no-animation">{{ $unit }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        @error('settings.'.$key)
                                            <div class="text-error text-xs mt-1">{{ $message }}</div>
                                        @enderror
                                    @else
                                        <div class="text-sm">{{ $state['is_overridden'] ? $state['value'] : $defaultShown }} {{ $unit }}</div>
                                    @endcan
                                    <div class="text-xs opacity-50 mt-1">{{ __('Default') }}: {{ $defaultShown }} {{ $unit }}</div>
                                </div>

                                <div class="lg:col-span-1 flex lg:justify-end">
                                    @can('industry-profiles.confirm')
                                        @if (! $state['confirmed_at'] && $field['status'] !== 'verified')
                                            <button type="submit" form="confirm-{{ $key }}" class="btn btn-xs btn-outline">{{ __('Confirm for my trade') }}</button>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @can('industry-profiles.update')
                <button class="btn btn-primary" type="submit">{{ __('Save settings') }}</button>
            </form>
        @endcan

        @can('industry-profiles.confirm')
            @foreach ($fieldDefinitions as $key => $field)
                <form id="confirm-{{ $key }}" method="POST" action="{{ route('industry-profiles.confirm', [$profile['key'], $key]) }}" class="hidden">@csrf</form>
            @endforeach
        @endcan

        {{-- Calculators --}}
        @if (! empty($profile['calculators']))
            @can('industry-profiles.calculate')
                <div class="card bg-base-100 shadow-xl">
                    <div class="card-body">
                        <span class="card-title text-base">{{ __('Calculators') }}</span>
                        <p class="text-xs text-base-content/60">{{ __('Informational only — results use your saved settings and are never posted.') }}</p>
                        <form class="hidden">@csrf</form>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-2">
                            @foreach ($profile['calculators'] as $calculator)
                                <div class="rounded-xl border border-base-300 p-4"
                                    x-data="industryCalculator('{{ route('industry-profiles.calculate', [$profile['key'], $calculator['key']]) }}')">
                                    <div class="font-semibold mb-3">{{ $t($calculator['title']) }}</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        @foreach ($calculator['inputs'] as $input)
                                            <label class="form-control">
                                                <span class="label-text text-xs mb-1">{{ $t($input['title']) }}</span>
                                                @if (isset($input['options']))
                                                    <select class="select select-bordered select-sm" x-model="inputs.{{ $input['key'] }}">
                                                        @foreach ($input['options'] as $option)
                                                            <option value="{{ $option }}">{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="number" step="any" class="input input-bordered input-sm" x-model="inputs.{{ $input['key'] }}" />
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm mt-3" @click="run()" :disabled="loading">{{ __('Calculate') }}</button>

                                    <template x-if="error">
                                        <div class="alert alert-error text-xs mt-3" x-text="error"></div>
                                    </template>
                                    <template x-if="result">
                                        <table class="table table-sm mt-3">
                                            <tbody>
                                                <template x-for="line in result.lines" :key="line.label">
                                                    <tr :class="line.emphasis ? 'font-bold' : ''">
                                                        <td x-text="line.label"></td>
                                                        <td class="text-end" x-text="format(line)"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </template>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('industryCalculator', (url) => ({
                            inputs: {},
                            result: null,
                            error: null,
                            loading: false,
                            format(line) {
                                const n = new Intl.NumberFormat(undefined, { maximumFractionDigits: 6 }).format(line.value);
                                return line.percent !== null && line.percent !== undefined ? n + '  (' + line.percent + '%)' : n;
                            },
                            async run() {
                                this.loading = true; this.error = null; this.result = null;
                                try {
                                    const token = document.querySelector('input[name=_token]').value;
                                    const res = await fetch(url, {
                                        method: 'POST',
                                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                                        body: JSON.stringify({ inputs: this.inputs }),
                                    });
                                    const data = await res.json();
                                    if (! res.ok) {
                                        this.error = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Error');
                                    } else {
                                        this.result = data;
                                    }
                                } catch (e) {
                                    this.error = String(e);
                                } finally {
                                    this.loading = false;
                                }
                            },
                        }));
                    });
                </script>
            @endcan
        @endif

        {{-- Not active yet --}}
        @if (! empty($profile['future']))
            <div class="card bg-base-100 shadow-xl opacity-80">
                <div class="card-body">
                    <span class="card-title text-base">{{ __('Not active yet') }}</span>
                    <ul class="space-y-2 text-sm">
                        @foreach ($profile['future'] as $item)
                            <li class="rounded-lg bg-base-200 px-4 py-2">
                                <div class="font-medium">{{ $t($item['title']) }}</div>
                                <div class="text-xs opacity-70">{{ $t($item['note']) }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- History --}}
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <span class="card-title text-base">{{ __('Change history') }}</span>
                @if ($history->isEmpty())
                    <div class="text-sm opacity-60">{{ __('No changes yet.') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Setting') }}</th><th>{{ __('Action') }}</th><th>{{ __('Old') }}</th><th>{{ __('New') }}</th></tr></thead>
                            <tbody>
                                @foreach ($history as $row)
                                    <tr>
                                        <td>{{ $row->created_at->format('Y-m-d H:i') }}</td>
                                        <td>{{ $row->setting_key }}</td>
                                        <td>{{ $actionLabels[$row->action] ?? $row->action }}</td>
                                        <td>{{ $row->old_value }}</td>
                                        <td>{{ $row->new_value }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
