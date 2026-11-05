<x-app-layout :title="__('دفترچه استعلام و قیمت‌های اعلام‌شده')">
    <x-show-message-bags />

<div class="container mx-auto px-4 py-6 max-w-6xl space-y-6" x-data="{ showModal: false, customerName: '', productName: '', quotedPrice: '', currency: 'toman', notes: '' }">
    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <span>🏷️</span>
                <span>دفترچه استعلام قیمت و حافظه هوش مصنوعی</span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                ثبت سریع قیمت‌های اعلام‌شده به مشتریان پشت تلفن یا حضوری، به همراه یادآوری هوشمند در چت کوپایلوت
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button @click="showModal = true" class="btn btn-primary gap-2 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                <span>قیمت دادن سریع (استعلام تلفنی)</span>
            </button>
            <a href="{{ route('copilot.index') }}" class="btn btn-outline btn-secondary gap-2">
                <span>🤖 پرسش از هوش مصنوعی</span>
            </a>
        </div>
    </div>

    <!-- AI Callout Banner -->
    <div class="card bg-indigo-50 border border-indigo-200 rounded-2xl shadow-xs">
        <div class="card-body p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    💡
                </div>
                <div>
                    <h3 class="font-bold text-indigo-950 text-sm">قابلیت استعلام صوتی و متنی با هوش مصنوعی نپتون</h3>
                    <p class="text-xs text-indigo-800 mt-0.5">
                        کافیست در چت کوپایلوت بنویسید یا بگویید: <code class="bg-indigo-100 text-indigo-900 px-1.5 py-0.5 rounded font-bold font-mono">«به علی قیمت گلس چند دادم؟»</code> تا هوش مصنوعی فوراً قیمت را برای شما بازخوانی کند!
                    </p>
                </div>
            </div>
            <a href="{{ route('copilot.index') }}" class="btn btn-sm btn-indigo text-xs bg-indigo-600 hover:bg-indigo-700 text-white border-none">
                امتحان در چت مالی
            </a>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl">
        <div class="card-body p-4">
            <form action="{{ route('price-quotes.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="label text-xs font-bold text-slate-600">جستجو بر اساس نام طرف‌حساب:</label>
                    <input type="text" name="customer" value="{{ $customerSearch }}" placeholder="مثلاً: علی، محمد، رضا..." class="input input-sm input-bordered w-full" />
                </div>
                <div>
                    <label class="label text-xs font-bold text-slate-600">جستجو بر اساس نام کالا:</label>
                    <input type="text" name="product" value="{{ $productSearch }}" placeholder="مثلاً: گلس، کاور، شارژر..." class="input input-sm input-bordered w-full" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-neutral flex-1">جستجو و فیلتر</button>
                    @if($customerSearch || $productSearch)
                        <a href="{{ route('price-quotes.index') }}" class="btn btn-sm btn-ghost">پاک‌سازی</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Quotes Table -->
    <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl overflow-hidden">
        <div class="card-body p-0">
            @if($quotes->isEmpty())
                <div class="text-center py-16 space-y-3">
                    <div class="text-5xl">🏷️</div>
                    <h3 class="font-bold text-slate-700 text-base">هنوز استعلام قیمتی ثبت نشده است</h3>
                    <p class="text-xs text-slate-400 max-w-md mx-auto">
                        هنگامی که مشتریان تلفنی یا حضوری قیمت می‌خواهند، با دکمه «قیمت دادن سریع» ثبت کنید تا نرخ‌های متفاوت برای مشتریان مختلف فراموش نشود.
                    </p>
                    <button @click="showModal = true" class="btn btn-sm btn-primary mt-2">ثبت اولین قیمت</button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                            <tr>
                                <th>#</th>
                                <th>طرف‌حساب (مشتری/همکار)</th>
                                <th>کالای مورد استعلام</th>
                                <th>قیمت اعلام‌شده</th>
                                <th>یادداشت / شرایط</th>
                                <th>تاریخ و زمان</th>
                                <th class="text-center">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($quotes as $index => $q)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="font-mono text-xs text-slate-400">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="font-bold text-slate-800">{{ $q->customer_name }}</div>
                                        @if($q->customer_phone)
                                            <div class="text-xs text-slate-400 font-mono" dir="ltr">{{ $q->customer_phone }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost font-medium text-slate-700">{{ $q->product_name }}</span>
                                    </td>
                                    <td>
                                        <span class="font-black text-emerald-700 text-base font-mono">
                                            {{ number_format($q->quoted_price) }}
                                        </span>
                                        <span class="text-xs text-slate-500 mr-1">{{ $q->currency === 'rial' ? 'ریال' : 'تومان' }}</span>
                                    </td>
                                    <td>
                                        <span class="text-xs text-slate-500">{{ $q->notes ?: '—' }}</span>
                                    </td>
                                    <td class="text-xs text-slate-400 font-mono">
                                        {{ $q->created_at ? $q->created_at->format('Y/m/d H:i') : 'امروز' }}
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ route('price-quotes.destroy', $q) }}" method="POST" onsubmit="return confirm('آیا از حذف این قیمت اطمینان دارید؟');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-xs text-error hover:bg-error/10">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Price Quote Modal -->
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" x-transition>
        <div @click.away="showModal = false" class="bg-base-100 rounded-3xl max-w-lg w-full p-6 space-y-5 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-black text-slate-800 text-lg flex items-center gap-2">
                    <span>⚡</span>
                    <span>قیمت دادن سریع (استعلام تلفنی / حضوری)</span>
                </h3>
                <button @click="showModal = false" class="btn btn-circle btn-ghost btn-sm text-slate-400">✕</button>
            </div>

            <form action="{{ route('price-quotes.store') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Customer -->
                <div>
                    <label class="label font-bold text-xs text-slate-700">نام طرف‌حساب (مشتری یا همکار):</label>
                    <input type="text" name="customer_name" required x-model="customerName" list="customerList" placeholder="مثلاً: علی، مهندس محمدی..." class="input input-bordered w-full" autofocus />
                    <datalist id="customerList">
                        @foreach($customers as $c)
                            <option value="{{ $c->name }}">{{ $c->mobile }}</option>
                        @endforeach
                    </datalist>
                </div>

                <!-- Product -->
                <div>
                    <label class="label font-bold text-xs text-slate-700">کالا یا خدمات مورد استعلام:</label>
                    <input type="text" name="product_name" required x-model="productName" list="productList" placeholder="مثلاً: گلس سرامیکی، قاب سیلیکونی..." class="input input-bordered w-full" />
                    <datalist id="productList">
                        @foreach($products as $p)
                            <option value="{{ $p->name }}">{{ number_format($p->selling_price) }} ریال</option>
                        @endforeach
                    </datalist>
                </div>

                <!-- Price & Currency -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2">
                        <label class="label font-bold text-xs text-slate-700">قیمت اعلام‌شده:</label>
                        <input type="number" name="quoted_price" required min="0" x-model="quotedPrice" placeholder="مثلاً: 40000" class="input input-bordered w-full font-mono text-left font-bold text-base" />
                    </div>
                    <div>
                        <label class="label font-bold text-xs text-slate-700">واحد پول:</label>
                        <select name="currency" x-model="currency" class="select select-bordered w-full font-bold">
                            <option value="toman">تومان</option>
                            <option value="rial">ریال</option>
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="label font-bold text-xs text-slate-700">یادداشت / توضیحات اختیاری:</label>
                    <input type="text" name="notes" x-model="notes" placeholder="مثلاً: تماس تلفنی، قیمت عمده‌فروشی، تحویل فردا..." class="input input-bordered w-full text-xs" />
                </div>

                <div class="flex items-center justify-between pt-3 border-t">
                    <button type="button" @click="showModal = false" class="btn btn-ghost btn-sm">انصراف</button>
                    <button type="submit" class="btn btn-primary px-8">ثبت و ذخیره در حافظه</button>
                </div>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
