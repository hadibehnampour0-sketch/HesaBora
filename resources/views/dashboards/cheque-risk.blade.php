<x-app-layout :title="__('Cheque risk')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        <div>
            <h1 class="text-xl font-bold">{{ __('Cheque risk') }}</h1>
            <p class="text-sm text-base-content/60">{{ __('Cheques that are overdue, bounced or due soon, for money you will receive and money you must pay.') }}</p>
        </div>

        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm">{{ __('Due within (days)') }}</label>
            <input type="number" min="1" name="due_soon_days" value="{{ $days }}" class="input input-bordered input-sm w-24">
            <button class="btn btn-sm btn-outline">{{ __('Show') }}</button>
        </form>

        @foreach (['receivable' => __('Cheques to receive'), 'payable' => __('Cheques to pay')] as $side => $sideTitle)
            @php $data = $report[$side]; @endphp
            <div class="today-panel">
                <div class="flex items-center justify-between">
                    <div class="today-panel-title">{{ $sideTitle }}</div>
                    <span class="text-sm">{{ __('Outstanding') }}: <b>{{ number_format($data['total_outstanding']) }}</b></span>
                </div>
                @foreach (['bounced' => [__('Bounced'), 'badge-error'], 'overdue' => [__('Overdue'), 'badge-warning'], 'due_soon' => [__('Due soon'), 'badge-info']] as $bucket => [$label, $badge])
                    <div class="mt-3">
                        <span class="badge {{ $badge }} badge-sm">{{ $label }} ({{ $data[$bucket]->count() }})</span>
                        <ul class="mt-1 divide-y divide-base-200 text-sm">
                            @foreach ($data[$bucket] as $cheque)
                                <li class="flex justify-between py-1">
                                    <span>{{ $cheque->title ?: $cheque->cheque_number }} <span class="text-xs text-base-content/50">{{ $cheque->due_date ? formatDate($cheque->due_date) : '' }}</span></span>
                                    <b>{{ number_format((float) $cheque->amount) }}</b>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</x-app-layout>
