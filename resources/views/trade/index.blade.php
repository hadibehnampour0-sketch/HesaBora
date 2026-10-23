<x-app-layout :title="__('تهاتر و تبادل اقلام با مغازه‌های همکار')">
    <x-show-message-bags />

<div class="container mx-auto px-4 py-6 max-w-6xl space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b pb-4">
        <div>
            <h1 class="text-2xl font-black text-slate-800 flex items-center gap-2">
                <span>🔄</span>
                <span>تهاتر و تبادل اقلام با مغازه‌های همکار (Trade & Barter)</span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                مدیریت جابه‌جایی موقت و امانی اجناس با همکاران بازار، ثبت کسری/مازاد و تسویه نهایی شبانه یا ادواری
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('trades.create') }}" class="btn btn-primary gap-2 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                <span>تشکیل پرونده تبادل جدید</span>
            </a>
            <a href="{{ route('copilot.index') }}" class="btn btn-outline btn-secondary gap-2">
                <span>🤖 ثبت صوتی/محاوره‌ای</span>
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card bg-base-100 border border-slate-200 shadow-sm p-4 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xl">
                ⏳
            </div>
            <div>
                <div class="text-xs text-slate-400 font-bold">تبادل‌های باز و در جریان</div>
                <div class="text-2xl font-black text-slate-800 mt-0.5">{{ $openCount }} پرونده فعال</div>
            </div>
        </div>

        <div class="card bg-base-100 border border-slate-200 shadow-sm p-4 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-xl">
                ✅
            </div>
            <div>
                <div class="text-xs text-slate-400 font-bold">پرونده‌های تسویه و بسته‌شده</div>
                <div class="text-2xl font-black text-slate-800 mt-0.5">{{ $settledCount }} تبادل</div>
            </div>
        </div>

        <div class="card bg-indigo-50 border border-indigo-200 shadow-sm p-4 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-bold text-xl">
                ⚡
            </div>
            <div>
                <div class="text-xs text-indigo-700 font-bold">مزیت حسابداری دخل</div>
                <div class="text-xs text-indigo-900 mt-0.5 font-medium leading-relaxed">عدم ایجاد بار مالیاتی بیهوده برای قرض و تبادل موقت اقلام</div>
            </div>
        </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="tabs tabs-boxed bg-slate-100 p-1 rounded-xl w-fit">
        <a href="{{ route('trades.index', ['status' => 'open']) }}" class="tab tab-sm {{ $status === 'open' ? 'tab-active font-bold' : '' }}">
            در جریان (باز)
        </a>
        <a href="{{ route('trades.index', ['status' => 'settled']) }}" class="tab tab-sm {{ $status === 'settled' ? 'tab-active font-bold' : '' }}">
            تسویه‌شده
        </a>
        <a href="{{ route('trades.index', ['status' => 'all']) }}" class="tab tab-sm {{ $status === 'all' ? 'tab-active font-bold' : '' }}">
            همه موارد
        </a>
    </div>

    <!-- Exchanges Table -->
    <div class="card bg-base-100 border border-slate-200 shadow-sm rounded-2xl overflow-hidden">
        <div class="card-body p-0">
            @if($exchanges->isEmpty())
                <div class="text-center py-16 space-y-3">
                    <div class="text-5xl">🔄</div>
                    <h3 class="font-bold text-slate-700 text-base">هیچ پرونده تبادلی در این بخش یافت نشد</h3>
                    <p class="text-xs text-slate-400 max-w-md mx-auto">
                        می‌توانید با مغازه‌های همسایه پرونده تبادل تشکیل دهید و هر زمان کاور، گلس یا هر جنسی رد و بدل شد به سادگی ثبت کنید.
                    </p>
                    <a href="{{ route('trades.create') }}" class="btn btn-sm btn-primary mt-2">شروع تبادل جدید</a>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-200">
                            <tr>
                                <th>#</th>
                                <th>عنوان تبادل</th>
                                <th>طرف‌حساب (مغازه همکار)</th>
                                <th>وضعیت</th>
                                <th>تعداد اقلام ثبت‌شده</th>
                                <th>خالص موقعیت ریالی</th>
                                <th>تاریخ ایجاد</th>
                                <th class="text-center">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($exchanges as $ex)
                                @php
                                    $summary = $ex->calculateValuationSummary();
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="font-mono text-xs text-slate-400">{{ $ex->id }}</td>
                                    <td>
                                        <a href="{{ route('trades.show', $ex) }}" class="font-bold text-primary hover:underline">
                                            {{ $ex->title }}
                                        </a>
                                    </td>
                                    <td>
                                        <div class="font-bold text-slate-800">{{ $ex->customer?->name }}</div>
                                        @if($ex->customer?->mobile)
                                            <div class="text-xs text-slate-400 font-mono" dir="ltr">{{ $ex->customer->mobile }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ex->status === 'open')
                                            <span class="badge badge-warning text-xs font-bold gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse"></span>
                                                در جریان (باز)
                                            </span>
                                        @else
                                            <span class="badge badge-success text-xs text-white font-bold">
                                                تسویه‌شده
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        <span class="font-bold text-slate-700">{{ $ex->items->count() }} ردیف</span>
                                        <span class="text-slate-400 text-[11px] mr-1">({{ $summary['open_out_count'] }} قلم داده‌شده / {{ $summary['open_in_count'] }} قلم گرفته‌شده)</span>
                                    </td>
                                    <td>
                                        @if($summary['net_valuation'] > 0)
                                            <span class="font-bold text-emerald-700 font-mono text-xs">
                                                +{{ number_format($summary['net_valuation']) }} ریال طلبکاریم
                                            </span>
                                        @elseif($summary['net_valuation'] < 0)
                                            <span class="font-bold text-rose-700 font-mono text-xs">
                                                {{ number_format(abs($summary['net_valuation'])) }} ریال بدهکاریم
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400">سربه‌سر (صفر)</span>
                                        @endif
                                    </td>
                                    <td class="text-xs text-slate-400 font-mono">
                                        {{ $ex->created_at ? $ex->created_at->format('Y/m/d') : 'امروز' }}
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('trades.show', $ex) }}" class="btn btn-sm btn-outline btn-primary">
                                            مدیریت و کاردکس
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-4 border-t">
                    {{ $exchanges->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
