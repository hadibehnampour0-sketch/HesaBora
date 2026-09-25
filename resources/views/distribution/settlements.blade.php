<x-app-layout :title="__('Daily settlements')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4">
        @include('distribution._nav')

        @can('distribution.settlements.store')
            <form method="POST" action="{{ route('distribution.settlements.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-6">
                @csrf
                <div class="today-panel-title sm:col-span-6">{{ __('Record the end of a visitor day') }}</div>
                <select name="user_id" class="select select-bordered sm:col-span-2" required>
                    <option value="">{{ __('Choose a visitor') }}</option>
                    @foreach ($visitors as $visitor)
                        <option value="{{ $visitor->user_id }}">{{ $visitor->user?->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="settlement_date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}" required>
                <input type="number" step="any" min="0" name="cash_collected" class="input input-bordered" placeholder="{{ __('Cash collected') }}" required>
                <input type="number" step="any" min="0" name="pos_collected" class="input input-bordered" placeholder="{{ __('POS collected') }}" required>
                <input type="number" step="any" min="0" name="cheque_collected" class="input input-bordered" placeholder="{{ __('Cheques collected') }}" required>
                <p class="text-xs text-base-content/60 sm:col-span-5">{{ __('The invoiced total is filled in from the invoices of that visitor for the day; enter what was physically collected.') }}</p>
                <button class="btn btn-primary btn-sm">{{ __('Save draft') }}</button>
            </form>
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th><th>{{ __('Visitor') }}</th><th>{{ __('Invoiced') }}</th>
                        <th>{{ __('Collected') }}</th><th>{{ __('Difference') }}</th><th>{{ __('Status') }}</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($settlements as $settlement)
                        @php
                            $collected = (float) $settlement->cash_collected + (float) $settlement->pos_collected + (float) $settlement->cheque_collected;
                            $difference = $collected - (float) $settlement->total_invoiced_amount;
                        @endphp
                        <tr>
                            <td>{{ formatDate($settlement->settlement_date) }}</td>
                            <td>{{ $settlement->user?->name }}</td>
                            <td>{{ number_format((float) $settlement->total_invoiced_amount) }}</td>
                            <td>{{ number_format($collected) }}</td>
                            <td class="{{ abs($difference) < 0.5 ? '' : 'text-error font-semibold' }}">{{ number_format($difference) }}</td>
                            <td><span class="badge badge-sm">{{ $settlement->status->value }}</span></td>
                            <td class="text-end">
                                @if ($settlement->status === \App\Enums\DailySettlementStatus::DRAFT)
                                    @can('distribution.settlements.submit')
                                        <form method="POST" action="{{ route('distribution.settlements.submit', $settlement) }}">@csrf<button class="btn btn-xs btn-outline">{{ __('Submit') }}</button></form>
                                    @endcan
                                @elseif ($settlement->status === \App\Enums\DailySettlementStatus::SUBMITTED)
                                    @can('distribution.settlements.approve')
                                        <form method="POST" action="{{ route('distribution.settlements.approve', $settlement) }}">@csrf<button class="btn btn-xs btn-primary">{{ __('Approve') }}</button></form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-sm text-base-content/60">{{ __('No settlements yet.') }}</td></tr>
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
