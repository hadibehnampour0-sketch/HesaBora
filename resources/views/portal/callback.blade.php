<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتیجه پرداخت | {{ $link->company?->name ?? 'سیستم حسابداری دخل' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.23/dist/full.min.css" rel="stylesheet" type="text/css" />
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Tahoma, sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full">
        <div class="card bg-base-100 shadow-xl border border-slate-200">
            <div class="card-body p-6 text-center space-y-4">

                @if($result->successful)
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>

                    <h2 class="text-2xl font-black text-emerald-800">پرداخت با موفقیت انجام شد</h2>
                    <p class="text-sm text-slate-600">رسید تراکنش شما ثبت گردید و سند مالی به روز شد.</p>

                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-right space-y-2 mt-4">
                        <div class="flex justify-between">
                            <span class="text-slate-500">کد رهگیری بانکی (RRN):</span>
                            <span class="font-mono font-bold text-slate-800">{{ $result->referenceNumber }}</span>
                        </div>
                        @if($result->cardPan)
                            <div class="flex justify-between">
                                <span class="text-slate-500">شماره کارت پرداخت‌کننده:</span>
                                <span class="font-mono text-slate-800">{{ $result->cardPan }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-slate-500">مبلغ پرداختی:</span>
                            <span class="font-mono font-bold text-emerald-600">{{ number_format($link->amount) }} ریال</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">شماره فاکتور:</span>
                            <span class="font-mono text-slate-800">{{ $link->invoice?->number ?? '-' }}</span>
                        </div>
                    </div>
                @else
                    <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </div>

                    <h2 class="text-2xl font-black text-rose-800">پرداخت ناموفق بود</h2>
                    <p class="text-sm text-slate-600">{{ $result->errorMessage ?: 'تراکنش پرداخت توسط درگاه یا کاربر لغو گردید.' }}</p>

                    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs p-3 rounded-xl mt-4">
                        چنانچه مبلغی از حساب شما کسر شده است، ظرف حداکثر ۷۲ ساعت توسط شبکه بانکی شاپرک برگشت داده خواهد شد.
                    </div>
                @endif

                <div class="pt-4 flex gap-3">
                    <a href="{{ route('portal.invoice.show', ['token' => $link->token]) }}" class="btn btn-primary flex-1">
                        بازگشت به فاکتور
                    </a>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
