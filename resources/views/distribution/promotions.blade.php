<x-app-layout :title="__('Promotions')">
    <x-show-message-bags />

    <div class="mx-auto max-w-5xl space-y-4" x-data="{ type: '{{ old('type', 'tiered_discount') }}' }">
        @include('distribution._nav')

        @can('distribution.promotions.store')
            <form method="POST" action="{{ route('distribution.promotions.store') }}" class="today-panel grid grid-cols-1 gap-3 sm:grid-cols-4">
                @csrf
                <div class="today-panel-title sm:col-span-4">{{ __('New promotion') }}</div>
                <input type="text" name="title" class="input input-bordered sm:col-span-2" placeholder="{{ __('Promotion title') }}" maxlength="255" required>
                <select name="type" x-model="type" class="select select-bordered">
                    @foreach (\App\Enums\PromotionType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <select name="customer_group_id" class="select select-bordered">
                    <option value="">{{ __('All customer groups') }}</option>
                    @foreach ($customerGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="starts_at" data-jdp autocomplete="off" readonly class="input input-bordered" placeholder="{{ __('Starts') }}">
                <input type="text" name="ends_at" data-jdp autocomplete="off" readonly class="input input-bordered" placeholder="{{ __('Ends') }}">

                <div class="sm:col-span-4" x-show="type === 'tiered_discount'">
                    <p class="mb-2 text-xs text-base-content/60">{{ __('The more they buy, the bigger the discount. The highest tier reached applies.') }}</p>
                    <select name="product_id" class="select select-bordered select-sm mb-2 w-full sm:w-1/2">
                        <option value="">{{ __('Whole cart quantity') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                        @foreach ([0, 1, 2] as $i)
                            <input type="number" step="any" min="0" name="tiers[{{ $i }}][min_qty]" class="input input-bordered input-sm sm:col-span-1" placeholder="{{ __('From quantity') }}">
                            <input type="number" step="any" min="0" max="100" name="tiers[{{ $i }}][discount_percent]" class="input input-bordered input-sm sm:col-span-1" placeholder="{{ __('Discount %') }}">
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:col-span-4 sm:grid-cols-4" x-show="type === 'bonus_goods'" style="display: none">
                    <p class="col-span-full text-xs text-base-content/60">{{ __('Buy a quantity of one product and get another product free.') }}</p>
                    <select name="buy_product_id" class="select select-bordered select-sm" :disabled="type !== 'bonus_goods'">
                        <option value="">{{ __('Product they buy') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="any" min="0" name="buy_qty" class="input input-bordered input-sm" placeholder="{{ __('Buy quantity') }}" :disabled="type !== 'bonus_goods'">
                    <select name="free_product_id" class="select select-bordered select-sm" :disabled="type !== 'bonus_goods'">
                        <option value="">{{ __('Free product') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="any" min="0" name="free_qty" class="input input-bordered input-sm" placeholder="{{ __('Free quantity') }}" :disabled="type !== 'bonus_goods'">
                </div>

                <div class="sm:col-span-4 flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Add promotion') }}</button></div>
            </form>
        @endcan

        <div class="today-panel overflow-x-auto p-0">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Rule') }}</th><th>{{ __('Period') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($promotions as $promotion)
                        @php $rules = $promotion->rules; @endphp
                        <tr>
                            <td>{{ $promotion->title }} @if ($promotion->customerGroup)<span class="badge badge-outline badge-xs">{{ $promotion->customerGroup->name }}</span>@endif</td>
                            <td>{{ $promotion->type->label() }}</td>
                            <td class="text-xs">
                                @if ($promotion->type === \App\Enums\PromotionType::TIERED_DISCOUNT)
                                    {{ $productNames[$rules['product_id'] ?? 0] ?? __('Whole cart quantity') }}:
                                    @foreach ($rules['tiers'] ?? [] as $tier)
                                        {{ $tier['min_qty'] }}+ → {{ $tier['discount_percent'] }}%@unless ($loop->last), @endunless
                                    @endforeach
                                @else
                                    {{ $rules['buy_qty'] ?? '' }} × {{ $productNames[$rules['buy_product_id'] ?? 0] ?? '?' }}
                                    → {{ $rules['free_qty'] ?? '' }} × {{ $productNames[$rules['free_product_id'] ?? 0] ?? '?' }}
                                @endif
                            </td>
                            <td class="text-xs">{{ $promotion->starts_at ? formatDate($promotion->starts_at) : '…' }} – {{ $promotion->ends_at ? formatDate($promotion->ends_at) : '…' }}</td>
                            <td><span class="badge badge-sm {{ $promotion->is_active ? 'badge-success' : 'badge-ghost' }}">{{ $promotion->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td class="flex justify-end gap-1">
                                @can('distribution.promotions.update')
                                    <form method="POST" action="{{ route('distribution.promotions.update', $promotion) }}">@csrf @method('PUT')<button class="btn btn-ghost btn-xs">{{ $promotion->is_active ? __('Deactivate') : __('Activate') }}</button></form>
                                @endcan
                                @can('distribution.promotions.destroy')
                                    <form method="POST" action="{{ route('distribution.promotions.destroy', $promotion) }}" onsubmit="return confirm('{{ __('Delete this promotion?') }}')">@csrf @method('DELETE')<button class="btn btn-ghost btn-xs text-error">{{ __('Delete') }}</button></form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-sm text-base-content/60">{{ __('No promotions yet.') }}</td></tr>
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
