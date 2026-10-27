<x-app-layout :title="$product->name">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $product->name }}</h2>
            <div class="flex flex-wrap gap-2 mt-2">
                @if ($product->productgroup)
                    <a href="{{ route('product-groups.show', $product->productgroup) }}"
                        class="badge badge-lg badge-primary gap-2 hover:badge-primary hover:brightness-110 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        {{ $product->productgroup->name }}
                    </a>
                @endif

                @if ($product->oversell === 1)
                    <span class="badge badge-lg badge-warning">
                        {{ __('product_oversell_allowed') }}
                    </span>
                @endif

                @if ($product->location)
                    <span class="badge badge-lg badge-secondary gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $product->location }}
                    </span>
                @endif

                @if ($product->code)
                    <span class="badge badge-lg badge-accent gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                        </svg>
                        {{ $product->code }}
                    </span>
                @endif

                @if ($product->productWebsites && $product->productWebsites->count() > 0)
                    @foreach ($product->productWebsites as $website)
                        @php
                            $domain = parse_url($website->link, PHP_URL_HOST) ?? $website->link; // Extract domain from URL
                            $domain = preg_replace('/^www\./i', '', $domain); //  Remove 'www.' if present
                        @endphp
                        <a href="{{ $website->link }}" target="_blank" rel="noopener noreferrer"
                            class="badge badge-lg badge-info gap-2 hover:badge-info hover:brightness-110 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                            </svg>
                            {{ $domain }}
                        </a>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                <div class="stats shadow bg-success/10">
                    <div class="stat min-w-0">
                        <div class="stat-title">{{ __('Stock') }}</div>
                        <div class="stat-value text-success">{{ formatNumber($product->quantity ?? 0) }}</div>
                        <div class="stat-desc mt-2 flex w-full gap-1 overflow-x-auto pb-1">
                            @forelse ($product->warehouseStocks->sortBy('warehouse.name') as $stock)
                                <a href="{{ route('warehouses.show', $stock->warehouse) }}" class="badge badge-outline badge-sm min-w-[calc((100%_-_1rem)/5)] shrink-0 gap-1 link whitespace-nowrap">
                                    <span>{{ $stock->warehouse->name }}</span>
                                    <span class="font-semibold">{{ formatNumber($stock->quantity) }}</span>
                                </a>
                            @empty
                                <span>{{ __('No warehouse stock found.') }}</span>
                            @endforelse
                        </div>
                    </div>
                </div>
                <x-stat-card :title="__('Quantity warning')" :value="formatNumber($product->quantity_warning ?? 0)" :description="__('Alert Level')" type="warning" icon="warning" />
                <x-stat-card :title="__('VAT')" :value="formatNumber($product->vat ?? 0) . '%'" :description="__('Tax Rate')" type="info" icon="vat" />
            </div>

            <div class="divider text-lg font-semibold">{{ __('Pricing Information') }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-3">
                <x-stat-card :title="__('Selling Price')" :value="formatNumber($product->selling_price ?? 0)" type="info" />
                <x-stat-card :title="__('Average Cost')" :value="formatNumber($product->average_cost ?? 0)" type="info" />
                <x-stat-card :title="__('Last Cost Of Goods')" :value="formatNumber($product->lastCOG ?? 0)" type="info" />
                <x-stat-card :title="__('Sales profit')" :value="formatNumber($product->salesProfit ?? 0)" type="success" />
                <x-stat-card :title="__('Discount Formula')" :value="$product->discount_formula ?? __('None')" type="info" />
            </div>
            @can('reports.ledger')
                <div class="divider text-lg font-semibold">{{ __('Account Subjects') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-3">
                    <x-stat-card-link :title="__('Income Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->incomeSubject))" :link="route('transactions.index', ['subject_id' => $product->incomeSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="success" icon="income" />
                    <x-stat-card-link :title="__('COGS Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->cogsSubject))" :link="route('transactions.index', ['subject_id' => $product->cogsSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="error" icon="cogs" />
                    <x-stat-card-link :title="__('Inventory Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->inventorySubject))" :link="route('transactions.index', ['subject_id' => $product->inventorySubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="info" icon="inventory" />
                    <x-stat-card-link :title="__('Sales Returns Subject')" :value="formatNumber(\App\Services\SubjectService::sumSubject($product->salesReturnsSubject))" :link="route('transactions.index', ['subject_id' => $product->salesReturnsSubject->id])" :currency="config('amir.currency') ?? __('Rial')" type="warning" icon="returns" />
                </div>
            @endcan
            @if (isset($product->description) && $product->description)
                <div class="divider text-lg font-semibold">{{ __('Description') }}</div>
                <div class="alert bg-base-200 shadow-sm mb-6">
                    <div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>{{ $product->description }}</span>
                    </div>
                </div>
            @endif

            <div class="divider text-lg font-semibold">{{ __('ردیابی سریال و لات (بچ کالا)') }}</div>

            <x-page-quick-tip :title="__('راهنمای ردیابی سریال‌های یکتا و بچ ساخت (Lot)')">
                <ul class="list-disc list-inside space-y-1">
                    <li><b>{{ __('شماره سریال (Serial):') }}</b> {{ __('مخصوص اقلامی با کد منحصر‌به‌فرد (مانند IMEI موبایل، قطعات دارای کد گارانتی یا پلاک).') }}</li>
                    <li><b>{{ __('لات و سری ساخت (Lot):') }}</b> {{ __('مخصوص اقلام دارای تاریخ انقضا و سری تولید (مانند مواد شیمیایی، رنگ، دارویی یا خوراکی).') }}</li>
                    <li>{{ __('هنگام صدور فاکتور فروش، سریال تحویل‌شده به مشتری ثبت شده و در کارت گارانتی فاکتور درج می‌گردد.') }}</li>
                </ul>
            </x-page-quick-tip>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                {{-- Serial Numbers Card --}}
                <div class="card bg-base-200/60 shadow-sm border border-base-300">
                    <div class="card-body p-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-bold text-base flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                </svg>
                                {{ __('شماره سریال‌های کالا') }}
                                <span class="badge badge-sm badge-ghost">{{ $product->serialNumbers->count() }}</span>
                            </h3>
                            <button type="button" onclick="document.getElementById('add-serial-modal').showModal()" class="btn btn-sm btn-primary btn-outline gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('ثبت شماره سریال') }}
                            </button>
                        </div>

                        <div class="overflow-x-auto max-h-60 overflow-y-auto">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr>
                                        <th>{{ __('شماره سریال') }}</th>
                                        <th>{{ __('انبار') }}</th>
                                        <th>{{ __('وضعیت') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($product->serialNumbers as $serial)
                                        <tr class="hover">
                                            <td class="font-mono font-bold">{{ $serial->serial_number }}</td>
                                            <td>{{ $serial->warehouse?->name ?? '-' }}</td>
                                            <td>
                                                @if ($serial->status === \App\Enums\SerialNumberStatus::IN_STOCK)
                                                    <span class="badge badge-success badge-xs gap-1">{{ __('موجود در انبار') }}</span>
                                                @else
                                                    <span class="badge badge-neutral badge-xs gap-1">
                                                        {{ __('فروخته شده') }}
                                                        @if ($serial->invoice_id)
                                                            <a href="{{ route('invoices.show', $serial->invoice_id) }}" class="link link-hover">#{{ $serial->invoice_id }}</a>
                                                        @endif
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-gray-500">{{ __('هیچ شماره سریالی برای این کالا ثبت نشده است.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Lots / Batches Card --}}
                <div class="card bg-base-200/60 shadow-sm border border-base-300">
                    <div class="card-body p-4">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-bold text-base flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                {{ __('لات و سری ساخت (بچ)') }}
                                <span class="badge badge-sm badge-ghost">{{ $product->lots->count() }}</span>
                            </h3>
                            <button type="button" onclick="document.getElementById('add-lot-modal').showModal()" class="btn btn-sm btn-secondary btn-outline gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('ثبت لات جدید') }}
                            </button>
                        </div>

                        <div class="overflow-x-auto max-h-60 overflow-y-auto">
                            <table class="table table-xs w-full">
                                <thead>
                                    <tr>
                                        <th>{{ __('شماره لات') }}</th>
                                        <th>{{ __('موجودی') }}</th>
                                        <th>{{ __('انبار') }}</th>
                                        <th>{{ __('تاریخ انقضا') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($product->lots as $lot)
                                        <tr class="hover">
                                            <td class="font-mono font-bold">{{ $lot->lot_number }}</td>
                                            <td class="font-bold text-primary">{{ formatNumber($lot->quantity) }}</td>
                                            <td>{{ $lot->warehouse?->name ?? '-' }}</td>
                                            <td>
                                                @if ($lot->expiry_date)
                                                    <span class="{{ $lot->expiry_date->isPast() ? 'text-error font-bold' : '' }}">
                                                        {{ formatDate($lot->expiry_date) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-gray-500">{{ __('هیچ لات یا سری ساختی ثبت نشده است.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="divider text-lg font-semibold">{{ __('Transaction History') }}</div>
            <div class="overflow-x-auto ">
                <table class="table w-full mt-4 overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">{{ __('Date') }}</th>
                            <th class="px-4 py-3">{{ __('Invoice Number') }}</th>
                            <th class="px-4 py-3">{{ __('Customer Name') }}</th>
                            <th class="px-4 py-3">{{ __('Warehouse') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Buy') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Sell') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Buy Unit Price') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Ancillary Cost') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Sell Unit Price') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('OFF') }}</th>
                            <th class="px-4 py-3 text-center">{{ __('Remaining') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($historyItems as $item)
                            <tr class="hover:bg-base-300 {{ !$item->invoice->status->isApprovedOrSettled() ? 'opacity-50' : '' }}">
                                <td class="px-4 py-3">{{ formatDate($item->invoice->date) }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('invoices.show', $item->invoice_id) }}" class="link">{{ formatDocumentNumber($item->invoice->number) }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('customers.show', $item->invoice->customer_id) }}">{{ $item->invoice->customer->name }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($item->invoice->warehouse)
                                        <a href="{{ route('warehouses.show', $item->invoice->warehouse) }}" class="link">{{ $item->invoice->warehouse->name }}</a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY)
                                        <a href="{{ route('invoices.show', $item->invoice_id) }}"
                                            class="badge badge-success gap-2 hover:badge-success hover:brightness-110 transition-all">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                            {{ formatNumber($item->quantity) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::SELL)
                                        <a href="{{ route('invoices.show', $item->invoice_id) }}"
                                            class="badge badge-info gap-2 hover:badge-info hover:brightness-110 transition-all">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                                            </svg>
                                            {{ formatNumber($item->quantity) }}
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY)
                                        {{ formatNumber($item->unit_price) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $ancillaryCost = 0;
                                        if ($item->invoice->invoice_type === \App\Enums\InvoiceType::BUY) {
                                            $ancillaryCost =
                                                $item->invoice->ancillaryCosts->sum(function ($ac) {
                                                    return $ac->items->sum('amount');
                                                }) / $item->quantity;
                                        }
                                    @endphp
                                    @if ($ancillaryCost > 0)
                                        {{ formatNumber($ancillaryCost) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center font-semibold">
                                    @if ($item->invoice->invoice_type === \App\Enums\InvoiceType::SELL)
                                        {{ formatNumber($item->unit_price) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if ($item->unit_discount > 0)
                                        <span class="badge badge-warning">{{ formatNumber($item->unit_discount) }}</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-center">
                                    @if ($item->invoice->status->isApprovedOrSettled())
                                        @php
                                            $invoiceType = $item->invoice->invoice_type;
                                            $quantityAfter = in_array($invoiceType, [\App\Enums\InvoiceType::BUY, \App\Enums\InvoiceType::RETURN_SELL, \App\Enums\InvoiceType::VOID], true)
                                                ? $item->quantity_at + $item->quantity
                                                : $item->quantity_at - $item->quantity;
                                        @endphp
                                        <span
                                            class="badge {{ $quantityAfter < 0 ? 'badge-error' : ($quantityAfter < $product->quantity_warning ? 'badge-warning' : 'badge-success') }} font-bold">
                                            {{ formatNumber($quantityAfter) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-8 text-gray-500">
                                    {{ __('No transactions found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($historyItems->hasPages())
                <div class="mt-6 flex justify-center">
                    {{ $historyItems->links() }}
                </div>
            @endif

            <div class="card-actions justify-between mt-8">
                <a href="{{ route('products.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <div class="flex flex-wrap gap-2">
                    @can('products.recalculate-quantity')
                        <form method="POST" action="{{ route('products.recalculate-quantity', $product) }}"
                            onsubmit="return confirm('{{ __('Products stock will Recalculate from all fiscal years. are you sure?') }}')">
                            @csrf
                            <button type="submit" class="btn btn-outline gap-2">{{ __('Recalculate stock') }}</button>
                        </form>
                    @endcan
                    @can('products.update')
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Edit') }}
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Add Serial Number --}}
    <dialog id="add-serial-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                </svg>
                {{ __('ثبت شماره سریال جدید') }}
            </h3>
            <form action="{{ route('product-serial-numbers.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                
                <div class="form-control mb-3">
                    <label class="label"><span class="label-text font-semibold">{{ __('انتخاب انبار') }}</span></label>
                    <select name="warehouse_id" class="select select-bordered w-full" required>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-control mb-4">
                    <label class="label"><span class="label-text font-semibold">{{ __('شماره سریال') }}</span></label>
                    <input type="text" name="serial_number" class="input input-bordered w-full font-mono" placeholder="مثال: SN-98421045" required maxlength="150">
                </div>

                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('add-serial-modal').close()" class="btn btn-ghost">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('ثبت شماره سریال') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>

    {{-- Modal Add Lot --}}
    <dialog id="add-lot-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                {{ __('ثبت لات و سری ساخت (بچ)') }}
            </h3>
            <form action="{{ route('product-lots.store') }}" method="POST">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                
                <div class="form-control mb-3">
                    <label class="label"><span class="label-text font-semibold">{{ __('انتخاب انبار') }}</span></label>
                    <select name="warehouse_id" class="select select-bordered w-full" required>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">{{ __('شماره لات / بچ') }}</span></label>
                        <input type="text" name="lot_number" class="input input-bordered w-full font-mono" placeholder="مثال: LOT-2026-A1" required maxlength="100">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">{{ __('تعداد ورود') }}</span></label>
                        <input type="number" step="any" min="0.0001" name="quantity" class="input input-bordered w-full" placeholder="1" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">{{ __('تاریخ دریافت') }}</span></label>
                        <input type="date" name="received_date" value="{{ date('Y-m-d') }}" class="input input-bordered w-full" required>
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-semibold">{{ __('تاریخ انقضا (اختیاری)') }}</span></label>
                        <input type="date" name="expiry_date" class="input input-bordered w-full">
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" onclick="document.getElementById('add-lot-modal').close()" class="btn btn-ghost">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-secondary">{{ __('ثبت لات') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
    </dialog>
</x-app-layout>
