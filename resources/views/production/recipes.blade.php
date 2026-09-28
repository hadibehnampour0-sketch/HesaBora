<x-app-layout :title="__('Recipes')">
    <x-show-message-bags />

    <div class="mx-auto max-w-4xl space-y-4" x-data="{ rows: [0, 1, 2] }">
        @include('production._nav')

        <p class="text-sm text-base-content/60">{{ __('A recipe says: to make this many units of a product, you need this much of each material. Set it up once, then use it every time you produce.') }}</p>

        @can('production.recipes.store')
            <form method="POST" action="{{ route('production.recipes.store') }}" class="today-panel space-y-3">
                @csrf
                <div class="today-panel-title">{{ __('New recipe') }}</div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <select name="product_id" class="select select-bordered sm:col-span-2" required>
                        <option value="">{{ __('Product this recipe makes') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" class="input input-bordered" placeholder="{{ __('Recipe name') }}" maxlength="150" required>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <input type="number" step="any" min="0" name="output_quantity" class="input input-bordered" placeholder="{{ __('Output quantity (e.g. 15 units per batch)') }}" required>
                </div>

                <p class="text-xs text-base-content/60">{{ __('Materials needed for one batch of the output quantity above. Waste % is how much extra of that material is actually used up (spillage), beyond the exact amount.') }}</p>
                <template x-for="i in rows" :key="i">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                        <select :name="'items[' + i + '][product_id]'" class="select select-bordered select-sm sm:col-span-2">
                            <option value="">{{ __('Material') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->code }})</option>
                            @endforeach
                        </select>
                        <input type="number" step="any" min="0" :name="'items[' + i + '][quantity]'" class="input input-bordered input-sm" placeholder="{{ __('Quantity') }}">
                        <input type="text" :name="'items[' + i + '][unit]'" class="input input-bordered input-sm" placeholder="{{ __('Unit (e.g. kg)') }}" maxlength="30">
                        <input type="number" step="any" min="0" max="100" :name="'items[' + i + '][waste_percent]'" class="input input-bordered input-sm" placeholder="{{ __('Waste %') }}">
                    </div>
                </template>
                <button type="button" class="btn btn-ghost btn-xs" @click="rows.push(rows.length)">{{ __('Add another material') }}</button>

                <div class="flex justify-end"><button class="btn btn-primary btn-sm">{{ __('Save recipe') }}</button></div>
            </form>
        @endcan

        @forelse ($recipes as $recipe)
            <div class="today-panel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="font-bold">{{ $recipe->name }}</span>
                        <span class="text-xs text-base-content/60">→ {{ rtrim(rtrim(number_format((float) $recipe->output_quantity, 3), '0'), '.') }} × {{ $recipe->product?->name }}</span>
                        @unless ($recipe->is_active)<span class="badge badge-ghost badge-sm">{{ __('Inactive') }}</span>@endunless
                    </div>
                    <div class="flex gap-1">
                        @can('production.recipes.update')
                            <form method="POST" action="{{ route('production.recipes.update', $recipe) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="{{ $recipe->is_active ? 0 : 1 }}">
                                <button class="btn btn-ghost btn-xs">{{ $recipe->is_active ? __('Deactivate') : __('Activate') }}</button>
                            </form>
                        @endcan
                        @can('production.recipes.destroy')
                            <form method="POST" action="{{ route('production.recipes.destroy', $recipe) }}" onsubmit="return confirm('{{ __('Delete this recipe?') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-ghost btn-xs text-error">{{ __('Delete') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
                <ul class="mt-2 divide-y divide-base-200 text-sm">
                    @foreach ($recipe->items as $item)
                        <li class="flex justify-between py-1">
                            <span>{{ $item->product?->name }}</span>
                            <span class="text-base-content/60">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} {{ $item->unit }}
                                @if ($item->waste_percent > 0) <span class="text-warning">(+{{ rtrim(rtrim(number_format((float) $item->waste_percent, 2), '0'), '.') }}% {{ __('waste') }})</span>@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="alert text-sm">{{ __('No recipes yet. Add the first one above.') }}</div>
        @endforelse
    </div>
</x-app-layout>
