<x-app-layout :title="__('پرونده تبادل با ' . ($exchange->customer?->name ?? 'همکار'))">
    <x-show-message-bags />

<div class="container mx-auto px-4 py-6 max-w-6xl space-y-6" x-data="{ showAddItemModal: false, showReturnModal: false, showSettleModal: false, returnItemId: null, returnItemName: '', maxReturnQty: 1 }">
    <x-page-quick-tip :title="__('راهنمای پرونده تهاتر و تبادل اقلام با همکاران')">
        <ul class="list-disc list-inside space-y-1">
            <li><b>{{ __('اقلام تحویلی (دادن کالا):') }}</b> {{ __('کالاهایی که از انبار شما خارج و تحویل همکار شده است.') }}</li>
            <li><b>{{ __('اقلام دریافتی (گرفتن کالا):') }}</b> {{ __('کالاهایی که در ازای آن از همکار تحویل گرفته و به انبار اضافه شده است.') }}</li>
            <li><b>{{ __('تسویه حساب خودکار:') }}</b> {{ __('در زمان تسویه، سیستم مابه‌التفاوت ارزش اقلام را محاسبه کرده و سند دوبل متوازن صادر می‌کند.') }}</li>
        </ul>
    </x-page-quick-tip>

    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b pb-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-black text-slate-800">{{ $exchange->title }}</h1>
                @if($exchange->status === 'open')
                    <span class="badge badge-warning text-xs font-bold gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                        در جریان (باز)
                    </span>
                @else
                    <span class="badge badge-success text-xs text-white font-bold">
                        تسویه‌شده
                    </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 mt-1">
                طرف‌حساب همکار: <strong class="text-slate-800">{{ $exchange->customer?->name }}</strong>
                @if($exchange->customer?->mobile)
                    | تلفن: <span class="font-mono text-xs" dir="ltr">{{ $exchange->customer->mobile }}</span>
                @endif
                | تاریخ ثبت: <span class="font-mono text-xs">{{ $exchange->created_at->format('Y/m/d') }}</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($exchange->isOpen())
                <button @click="showAddItemModal = true" class="btn btn-outline btn-primary btn-sm gap-1">
                    + افزودن کالا
                </button>
                <button @click="showSettleModal = true" class="btn btn-success btn-sm text-white gap-1 shadow-sm">
                    ✓ تسویه نهایی تبادل
                </button>
            @endif
            <a href="{{ route('trades.index') }}" class="btn btn-ghost btn-sm">بازگشت</a>
        </div>
    </div>

    <!-- Net Position & Valuation Card -->
    <div class="card bg-slate-900 text-white rounded-3xl p-6 shadow-xl">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="space-y-2">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">خالص وضعیت حساب و تراز اقلام</div>
                <div class="text-3xl font-black font-mono">
                    @if($summary['net_valuation'] > 0)
                        <span class="text-emerald-400">+{{ number_format($summary['net_valuation']) }} ریال</span>
                        <span class="text-xs font-normal text-slate-300 mr-2">(ما از همکار طلبکاریم)</span>
                    @elseif($summary['net_valuation'] < 0)
                        <span class="text-rose-400">{{ number_format(abs($summary['net_valuation'])) }} ریال</span>
                        <span class="text-xs font-normal text-slate-300 mr-2">(ما به همکار بدهکاریم)</span>
                    @else
                        <span class="text-slate-200">سربه‌سر (۰ ریال)</span>
                    @endif
                </div>
                <div class="text-xs text-slate-400">
                    مجموع ارزش اقلام داده‌شده: <span class="font-mono text-slate-200">{{ number_format($summary['total_out_value']) }} ریال</span> |
                    مجموع ارزش اقلام گرفته‌شده: <span class="font-mono text-slate-200">{{ number_format($summary['total_in_value']) }} ریال</span>
                </div>
            </div>

            @if($exchange->isOpen())
                <div>
                    <button @click="showSettleModal = true" class="btn btn-primary text-white font-bold px-6">
                        تسویه شبانه / نهایی
                    </button>
                </div>
            @else
                <div class="bg-slate-800 p-3 rounded-xl border border-slate-700 text-xs space-y-1">
                    <div class="text-emerald-400 font-bold">✓ پرونده با موفقیت تسویه شده است</div>
                    <div class="text-slate-300">شیوه تسویه: {{ $exchange->settlement_notes }}</div>
                    <div class="text-slate-500 font-mono">{{ $exchange->settled_at?->format('Y/m/d H:i') }}</div>
                    @if($exchange->document_id)
                        <div class="pt-1">
                            <a href="{{ route('documents.show', $exchange->document_id) }}" class="btn btn-xs btn-outline btn-success gap-1 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                مشاهده سند حسابداری دوبل (#{{ $exchange->document_id }})
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Section 1: Items Given (OUT - داده شده به همکار) -->
    <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl overflow-hidden">
        <div class="card-header bg-slate-50 p-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                <h3 class="font-bold text-slate-800 text-base">📤 اقلام تحویل داده شده به همکار (امانی / خروج)</h3>
            </div>
            @if($exchange->isOpen())
                <button @click="showAddItemModal = true" class="btn btn-xs btn-outline btn-primary">+ قلم خروجی جدید</button>
            @endif
        </div>
        <div class="card-body p-0">
            @php $itemsOut = $exchange->itemsOut; @endphp
            @if($itemsOut->isEmpty())
                <div class="p-6 text-center text-xs text-slate-400">هنوز کالایی به همکار داده نشده است.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="table w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs border-b">
                            <tr>
                                <th>نام کالا</th>
                                <th>تعداد داده‌شده</th>
                                <th>تعداد مرجوع‌شده (برگشتی)</th>
                                <th>مانده نزد همکار</th>
                                <th>ارزش واحد (ریال)</th>
                                <th>ارزش باقیمانده</th>
                                @if($exchange->isOpen())
                                    <th class="text-center">عملیات عودت</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($itemsOut as $item)
                                <tr>
                                    <td class="font-bold text-slate-800">{{ $item->item_name }}</td>
                                    <td class="font-mono">{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td class="font-mono text-amber-600">{{ $item->returned_quantity }} {{ $item->unit }}</td>
                                    <td class="font-mono font-bold text-slate-800">
                                        @if($item->effective_quantity == 0)
                                            <span class="badge badge-xs badge-success text-white">تسویه جنسی شده</span>
                                        @else
                                            {{ $item->effective_quantity }} {{ $item->unit }}
                                        @endif
                                    </td>
                                    <td class="font-mono text-slate-600">{{ number_format($item->unit_price) }}</td>
                                    <td class="font-mono font-bold text-emerald-700">{{ number_format($item->effective_value) }} ریال</td>
                                    @if($exchange->isOpen())
                                        <td class="text-center">
                                            @if($item->effective_quantity > 0)
                                                <button type="button" @click="returnItemId = {{ $item->id }}; returnItemName = '{{ $item->item_name }}'; maxReturnQty = {{ $item->effective_quantity }}; showReturnModal = true;" class="btn btn-xs btn-outline btn-warning">
                                                    ثبت پس آوردن کالا
                                                </button>
                                            @else
                                                <span class="text-xs text-slate-400">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Section 2: Items Received (IN - تحویل گرفته شده از همکار) -->
    <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl overflow-hidden">
        <div class="card-header bg-slate-50 p-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                <h3 class="font-bold text-slate-800 text-base">📥 اقلام تحویل گرفته شده از همکار (امانی / ورود)</h3>
            </div>
            @if($exchange->isOpen())
                <button @click="showAddItemModal = true" class="btn btn-xs btn-outline btn-primary">+ قلم ورودی جدید</button>
            @endif
        </div>
        <div class="card-body p-0">
            @php $itemsIn = $exchange->itemsIn; @endphp
            @if($itemsIn->isEmpty())
                <div class="p-6 text-center text-xs text-slate-400">هنوز کالایی از همکار دریافت نشده است.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="table w-full text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs border-b">
                            <tr>
                                <th>نام کالا</th>
                                <th>تعداد گرفته‌شده</th>
                                <th>تعداد پس‌داده‌شده</th>
                                <th>مانده نزد ما</th>
                                <th>ارزش واحد (ریال)</th>
                                <th>ارزش باقیمانده</th>
                                @if($exchange->isOpen())
                                    <th class="text-center">عملیات عودت</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($itemsIn as $item)
                                <tr>
                                    <td class="font-bold text-slate-800">{{ $item->item_name }}</td>
                                    <td class="font-mono">{{ $item->quantity }} {{ $item->unit }}</td>
                                    <td class="font-mono text-amber-600">{{ $item->returned_quantity }} {{ $item->unit }}</td>
                                    <td class="font-mono font-bold text-slate-800">
                                        @if($item->effective_quantity == 0)
                                            <span class="badge badge-xs badge-success text-white">تسویه جنسی شده</span>
                                        @else
                                            {{ $item->effective_quantity }} {{ $item->unit }}
                                        @endif
                                    </td>
                                    <td class="font-mono text-slate-600">{{ number_format($item->unit_price) }}</td>
                                    <td class="font-mono font-bold text-rose-700">{{ number_format($item->effective_value) }} ریال</td>
                                    @if($exchange->isOpen())
                                        <td class="text-center">
                                            @if($item->effective_quantity > 0)
                                                <button type="button" @click="returnItemId = {{ $item->id }}; returnItemName = '{{ $item->item_name }}'; maxReturnQty = {{ $item->effective_quantity }}; showReturnModal = true;" class="btn btn-xs btn-outline btn-warning">
                                                    ثبت پس دادن به همکار
                                                </button>
                                            @else
                                                <span class="text-xs text-slate-400">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal 1: Add Item Modal -->
    <div x-show="showAddItemModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition>
        <div @click.away="showAddItemModal = false" class="bg-base-100 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-black text-slate-800 text-base">افزودن قلم کالا به پرونده تبادل</h3>
                <button @click="showAddItemModal = false" class="btn btn-circle btn-ghost btn-sm text-slate-400">✕</button>
            </div>

            <form action="{{ route('trades.items.add', $exchange) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="label text-xs font-bold text-slate-700">جهت جابه‌جایی:</label>
                    <select name="direction" required class="select select-bordered w-full font-bold">
                        <option value="out">📤 تحویل دادم به همکار (خروج)</option>
                        <option value="in">📥 تحویل گرفتم از همکار (ورود)</option>
                    </select>
                </div>

                <div>
                    <label class="label text-xs font-bold text-slate-700">نام کالا:</label>
                    <input type="text" name="item_name" required placeholder="مثلاً: کاور سیلیکونی، گلس..." class="input input-bordered w-full" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label text-xs font-bold text-slate-700">تعداد:</label>
                        <input type="number" name="quantity" required min="0.01" step="any" value="1" class="input input-bordered w-full font-mono font-bold" />
                    </div>
                    <div>
                        <label class="label text-xs font-bold text-slate-700">ارزش واحد (ریال):</label>
                        <input type="number" name="unit_price" min="0" value="0" class="input input-bordered w-full font-mono" />
                    </div>
                </div>

                <div>
                    <label class="label text-xs font-bold text-slate-700">یادداشت:</label>
                    <input type="text" name="notes" placeholder="اختیاری..." class="input input-bordered w-full text-xs" />
                </div>

                <div class="flex justify-between items-center pt-3 border-t">
                    <button type="button" @click="showAddItemModal = false" class="btn btn-ghost btn-sm">انصراف</button>
                    <button type="submit" class="btn btn-primary btn-sm px-6">ثبت در پرونده</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Return Item Modal -->
    <div x-show="showReturnModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition>
        <div @click.away="showReturnModal = false" class="bg-base-100 rounded-3xl max-w-sm w-full p-6 space-y-4 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-black text-slate-800 text-base">ثبت عودت / برگشت کالا</h3>
                <button @click="showReturnModal = false" class="btn btn-circle btn-ghost btn-sm text-slate-400">✕</button>
            </div>

            <form :action="`/trades/items/${returnItemId}/return`" method="POST" class="space-y-4">
                @csrf
                <div class="text-xs text-slate-600">
                    کالای در حال برگشت: <strong x-text="returnItemName" class="text-slate-800"></strong>
                </div>

                <div>
                    <label class="label text-xs font-bold text-slate-700">تعداد برگشتی / پس داده شده:</label>
                    <input type="number" name="returned_quantity" required min="0.01" :max="maxReturnQty" step="any" value="1" class="input input-bordered w-full font-mono font-bold text-lg" />
                    <span class="text-[11px] text-slate-400 mt-1 block">حداکثر تعداد قابل عودت: <span x-text="maxReturnQty"></span> عدد</span>
                </div>

                <div class="flex justify-between items-center pt-3 border-t">
                    <button type="button" @click="showReturnModal = false" class="btn btn-ghost btn-sm">انصراف</button>
                    <button type="submit" class="btn btn-warning btn-sm px-6 font-bold">ثبت عودت در کاردکس</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Settle Exchange Modal -->
    <div x-show="showSettleModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition>
        <div @click.away="showSettleModal = false" class="bg-base-100 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-black text-slate-800 text-base">تسویه نهایی تبادل اقلام</h3>
                <button @click="showSettleModal = false" class="btn btn-circle btn-ghost btn-sm text-slate-400">✕</button>
            </div>

            <form action="{{ route('trades.settle', $exchange) }}" method="POST" class="space-y-4">
                @csrf

                <div class="bg-slate-50 p-3 rounded-xl border text-xs space-y-1">
                    <div class="text-slate-500">خالص ارزش ریالی مابه‌التفاوت:</div>
                    <div class="text-base font-black font-mono {{ $summary['net_valuation'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ number_format(abs($summary['net_valuation'])) }} ریال
                        ({{ $summary['net_valuation'] >= 0 ? 'طلبکاریم' : 'بدهکاریم' }})
                    </div>
                </div>

                <div>
                    <label class="label text-xs font-bold text-slate-700">شیوه توافق و تسویه:</label>
                    <select name="settlement_type" required class="select select-bordered w-full font-bold">
                        <option value="goods">🤝 تسویه و توافق جنسی (سربه‌سر صاف شدیم)</option>
                        <option value="cash">💵 تسویه نقدی / پرداخت مابه‌التفاوت ریالی</option>
                        <option value="ledger">📑 ثبت و انتقال مانده به حساب دفتری شخص</option>
                    </select>
                </div>

                <div>
                    <label class="label text-xs font-bold text-slate-700">مبلغ توافقی نهایی (اختیاری جهت تخفیف یا گردکردن):</label>
                    <input type="number" name="agreed_cash_amount" value="{{ abs($summary['net_valuation']) }}" class="input input-bordered w-full font-mono" />
                </div>

                <div>
                    <label class="label text-xs font-bold text-slate-700">یادداشت تسویه:</label>
                    <input type="text" name="notes" placeholder="مثلاً: تسویه دستی در مغازه، کارت‌به‌کارت شد..." class="input input-bordered w-full text-xs" />
                </div>

                <div class="flex justify-between items-center pt-3 border-t">
                    <button type="button" @click="showSettleModal = false" class="btn btn-ghost btn-sm">انصراف</button>
                    <button type="submit" class="btn btn-success text-white btn-sm px-8 font-bold">بستن و تسویه پرونده</button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
