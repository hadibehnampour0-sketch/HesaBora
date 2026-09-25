<x-app-layout :title="__('Payment terms')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Payment terms') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Define instalment schedules such as 30% now, 40% after 30 days and 30% after 60 days, then apply one to an invoice.') }}</p>
        </div>

        @can('payment-terms.store')
            <form method="POST" action="{{ route('payment-terms.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('New payment term') }}</div>
                <input type="text" name="code" class="input input-bordered" placeholder="{{ __('Code') }}" maxlength="50" required>
                <input type="text" name="name" class="input input-bordered sm:col-span-2" placeholder="{{ __('Name') }}" maxlength="150" required>
                <input type="text" name="description" class="input input-bordered" placeholder="{{ __('Description') }}">
                <p class="text-xs text-base-content/60 sm:col-span-4">{{ __('Instalments: days after the invoice date and the percentage due. The percentages must add up to 100.') }}</p>
                @foreach ([0, 1, 2, 3] as $i)
                    <input type="number" min="0" name="lines[{{ $i }}][days_offset]" class="input input-bordered input-sm" placeholder="{{ __('Days after invoice') }}" @if ($i === 0) required @endif>
                    <input type="number" step="any" min="0" max="100" name="lines[{{ $i }}][percentage]" class="input input-bordered input-sm" placeholder="{{ __('Percent') }}" @if ($i === 0) required @endif>
                @endforeach
                <div class="sm:col-span-4 flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Add payment term') }}</button></div>
            </form>
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($terms as $term)
                        <tr>
                            <td>{{ $term->code }}</td>
                            <td>{{ $term->name }}</td>
                            <td class="text-xs">
                                @foreach ($term->lines as $line)
                                    {{ rtrim(rtrim(number_format((float) $line->percentage, 2), '0'), '.') }}% / {{ $line->days_offset }} {{ __('days') }}@unless ($loop->last) · @endunless
                                @endforeach
                            </td>
                            <td><span class="badge badge-sm {{ $term->is_active ? 'badge-success' : 'badge-ghost' }}">{{ $term->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td class="text-end">
                                @can('payment-terms.update')
                                    <form method="POST" action="{{ route('payment-terms.update', $term) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $term->is_active ? 0 : 1 }}">
                                        <button class="btn btn-ghost btn-xs">{{ $term->is_active ? __('Deactivate') : __('Activate') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-sm text-base-content/60">{{ __('No payment terms yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
