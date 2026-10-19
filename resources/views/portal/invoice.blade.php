<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهده فاکتور {{ $invoice?->number ?? '' }} | {{ $link->company?->name ?? 'سیستم حسابداری دخل' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Tahoma, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto space-y-6">

        @if(session('error'))
            <div class="alert alert-error shadow-sm rounded-xl">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success shadow-sm rounded-xl">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Header Card -->
        <div class="card bg-base-100 shadow-md border border-slate-200">
            <div class="card-body p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-slate-100 pb-5">
                    <div>
                        <h1 class="text-2xl font-black text-primary">{{ $link->company?->name ?? 'دخل (سیستم جامع مالی)' }}</h1>
                        <p class="text-sm text-slate-500 mt-1">سامانه پرداخت و مشاهده فاکتور الکترونیک</p>
                    </div>
                    <div class="text-left">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-slate-500">شماره فاکتور:</span>
                            <span class="font-bold text-lg text-slate-900 font-mono">{{ $invoice?->number ?? '-' }}</span>
                        </div>
                        <div class="text-sm text-slate-500 mt-1">
                            تاریخ: {{ $invoice?->date ? \Carbon\Carbon::parse($invoice->date)->toDateString() : '-' }}
                        </div>
                    </div>
                </div>

                <!-- Status Banner -->
                @if($link->isPaid())
                    <div class="alert alert-success rounded-xl mt-4 text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <div>
                            <div class="font-bold">این فاکتور با موفقیت تسویه و پرداخت شده است.</div>
                            <div class="text-xs opacity-90">کد رهگیری بانکی: {{ $link->gateway_reference }} | تاریخ پرداخت: {{ $link->paid_at }}</div>
                        </div>
                    </div>
                @elseif($link->isExpired())
                    <div class="alert alert-warning rounded-xl mt-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>مهلت پرداخت آنلاین این لینک منقضی شده است. لطفا با واحد مالی تماس بگیرید.</span>
                    </div>
                @endif

                <!-- Customer Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div>
                        <div class="text-xs text-slate-500">مشخصات خریدار / مشتری:</div>
                        <div class="font-bold text-slate-800 text-base mt-1">{{ $invoice?->customer?->name ?? 'مشتری عمومی' }}</div>
                        @if($invoice?->customer?->phone)
                            <div class="text-sm text-slate-600 mt-1">شماره تماس: <span class="font-mono">{{ $invoice->customer->phone }}</span></div>
                        @endif
                    </div>
                    <div class="md:text-left">
                        <div class="text-xs text-slate-500">وضعیت سند:</div>
                        <div class="mt-1">
                            @if($link->isPaid())
                                <span class="badge badge-success text-white font-medium">تسویه کامل</span>
                            @else
                                <span class="badge badge-warning font-medium">در انتظار پرداخت</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="overflow-x-auto mt-6">
                    <table class="table table-zebra w-full border border-slate-200 rounded-xl overflow-hidden">
                        <thead class="bg-slate-100 text-slate-700">
                            <tr>
                                <th class="w-12">#</th>
                                <th>شرح کالا / خدمات</th>
                                <th class="text-center">تعداد / مقدار</th>
                                <th class="text-left">قیمت واحد (ریال)</th>
                                <th class="text-left">مبلغ کل (ریال)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice?->items ?? [] as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="font-medium">{{ $row->product?->name ?? $row->title ?? 'کالا / خدمات' }}</td>
                                    <td class="text-center font-mono">{{ $row->count ?? 1 }}</td>
                                    <td class="text-left font-mono">{{ number_format($row->price ?? 0) }}</td>
                                    <td class="text-left font-mono font-bold">{{ number_format($row->final_price ?? ($row->count * $row->price)) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-slate-400">اطلاعات ردیف‌های فاکتور موجود نیست.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Invoice Financial Summary -->
                <div class="flex flex-col md:flex-row justify-between items-center gap-6 mt-6 pt-6 border-t border-slate-200">
                    <div class="flex gap-2">
                        <a href="{{ route('portal.invoice.print', ['token' => $link->token]) }}" target="_blank" class="btn btn-outline btn-sm gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                            نسخه چاپی
                        </a>
                    </div>

                    <div class="w-full md:w-80 space-y-2 text-sm">
                        <div class="flex justify-between text-slate-600">
                            <span>مبلغ کل فاکتور:</span>
                            <span class="font-mono font-medium">{{ number_format($invoice?->amount ?? $link->amount) }} ریال</span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-primary pt-2 border-t border-slate-100">
                            <span>مبلغ قابل پرداخت این لینک:</span>
                            <span class="font-mono text-lg text-emerald-600">{{ number_format($link->amount) }} ریال</span>
                        </div>
                    </div>
                </div>

                <!-- Pay Button Area -->
                @if(! $link->isPaid() && ! $link->isExpired())
                    <div class="mt-8 bg-emerald-50 border border-emerald-200 rounded-2xl p-6 text-center space-y-4">
                        <div>
                            <h3 class="text-lg font-black text-emerald-900">پرداخت امن و آنی آنلاین</h3>
                            <p class="text-xs text-emerald-700 mt-1">اتصال به شبکه پرداخت الکترونیک شاپرک و درگاه‌های عضو شتاب</p>
                        </div>

                        <form action="{{ route('portal.invoice.pay', ['token' => $link->token]) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-lg px-12 text-white font-bold text-lg shadow-lg hover:shadow-xl transition-all">
                                پرداخت آنلاین مبلغ {{ number_format($link->amount) }} ریال
                            </button>
                        </form>

                        <div class="text-[11px] text-slate-400">
                            پس از واریز، فاکتور شما بلافاصله در سیستم تسویه شده و رسید الکترونیک پیامک خواهد شد.
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</body>
</html>
