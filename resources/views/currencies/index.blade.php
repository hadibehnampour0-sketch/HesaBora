<x-app-layout :title="__('Currencies')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Currencies') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Define foreign currencies and record their exchange rate against the base currency by date.') }}</p>
        </div>

        @can('currencies.store')
            <form method="POST" action="{{ route('currencies.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-5">
                @csrf
                <div class="today-panel-title sm:col-span-5">{{ __('Add a currency') }}</div>
                <input type="text" name="code" class="input input-bordered" placeholder="{{ __('Code (e.g. USD)') }}" maxlength="10" required>
                <input type="text" name="name" class="input input-bordered" placeholder="{{ __('Name') }}" maxlength="100" required>
                <input type="text" name="symbol" class="input input-bordered" placeholder="{{ __('Symbol') }}" maxlength="10">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_base" value="1" class="checkbox checkbox-sm">{{ __('Base currency') }}</label>
                <button class="btn btn-primary btn-sm">{{ __('Add') }}</button>
            </form>
        @endcan

        @forelse ($currencies as $currency)
            <div class="today-panel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-bold">{{ $currency->code }}</span> {{ $currency->name }} {{ $currency->symbol }}
                        @if ($currency->is_base)<span class="badge badge-primary badge-sm">{{ __('Base') }}</span>@endif
                    </div>
                    @can('currencies.rates.store')
                        @unless ($currency->is_base)
                            <form method="POST" action="{{ route('currencies.rates.store', $currency) }}" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="number" step="any" min="0" name="rate" class="input input-bordered input-sm w-32" placeholder="{{ __('Rate') }}" required>
                                <input type="text" name="effective_date" data-jdp autocomplete="off" readonly class="input input-bordered input-sm w-32" value="{{ convertToJalali(now(), true) }}" required>
                                <button class="btn btn-sm btn-outline">{{ __('Record rate') }}</button>
                            </form>
                        @endunless
                    @endcan
                </div>
                @if ($currency->exchangeRates->isNotEmpty())
                    <ul class="mt-3 divide-y divide-base-200 text-sm">
                        @foreach ($currency->exchangeRates->sortByDesc('effective_date')->take(5) as $rate)
                            <li class="flex justify-between py-1"><span>{{ formatDate($rate->effective_date) }}</span><span class="font-semibold">{{ number_format((float) $rate->rate, 4) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <div class="alert text-sm">{{ __('No currencies yet.') }}</div>
        @endforelse
    </div>

    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endpush
</x-app-layout>
