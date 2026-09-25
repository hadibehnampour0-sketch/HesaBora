<x-app-layout :title="__('Industry Profiles')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <span class="card-title">{{ __('Industry Profiles') }}</span>
            <p class="text-sm text-base-content/70">
                {{ __('Each trade has its own dashboard of settings and calculators. Defaults carry an evidence badge; change any value to match your trade — no code change needed.') }}
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-4">
                @foreach ($cards as $card)
                    <div class="rounded-xl border border-base-300 p-4 flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('industry-profiles.show', $card['key']) }}" class="font-semibold text-lg hover:underline">{{ $card['title'] }}</a>
                            @if ($card['active'])
                                <span class="badge badge-success badge-sm">{{ __('Active') }}</span>
                            @else
                                <span class="badge badge-ghost badge-sm">{{ __('Inactive') }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-base-content/70 flex-1">{{ $card['description'] }}</p>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="badge badge-outline">{{ __(':count settings', ['count' => $card['fields']]) }}</span>
                            <span class="badge badge-outline">{{ __(':count calculators', ['count' => $card['calculators']]) }}</span>
                            @if ($card['pending']['needs_confirmation'] > 0)
                                <span class="badge badge-warning">{{ __(':count need confirmation', ['count' => $card['pending']['needs_confirmation']]) }}</span>
                            @endif
                            @if ($card['pending']['assumed'] > 0)
                                <span class="badge badge-info">{{ __(':count assumed', ['count' => $card['pending']['assumed']]) }}</span>
                            @endif
                        </div>
                        <a href="{{ route('industry-profiles.show', $card['key']) }}" class="btn btn-primary btn-sm self-start">{{ __('Open dashboard') }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
