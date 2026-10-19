<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>چاپ فاکتور {{ $invoice?->number ?? '' }}</title>
    <style>
        body { font-family: Tahoma, sans-serif; font-size: 13px; margin: 20px; direction: rtl; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { border: 1px solid #999; padding: 8px; text-align: center; }
        .table th { background-color: #f2f2f2; }
        .footer { margin-top: 30px; display: flex; justify-content: space-between; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 16px; cursor: pointer;">چاپ این صفحه</button>
    </div>

    <div class="header">
        <div>
            <h2>{{ $link->company?->name ?? 'فروشگاه / شرکت' }}</h2>
            <div>فاکتور فروش کالا و خدمات</div>
        </div>
        <div style="text-align: left;">
            <div>شماره فاکتور: <strong>{{ $invoice?->number ?? '-' }}</strong></div>
            <div>تاریخ: {{ $invoice?->date ? \Carbon\Carbon::parse($invoice->date)->toDateString() : '-' }}</div>
        </div>
    </div>

    <div style="margin-bottom: 15px;">
        <strong>خریدار:</strong> {{ $invoice?->customer?->name ?? 'مشتری محترم' }}
        @if($invoice?->customer?->phone) | تلفن: {{ $invoice->customer->phone }} @endif
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 30px;">ردیف</th>
                <th>شرح کالا / خدمات</th>
                <th style="width: 80px;">تعداد</th>
                <th style="width: 120px;">قیمت واحد (ریال)</th>
                <th style="width: 140px;">مبلغ کل (ریال)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice?->items ?? [] as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: right;">{{ $row->product?->name ?? $row->title ?? 'کالا' }}</td>
                    <td>{{ $row->count ?? 1 }}</td>
                    <td>{{ number_format($row->price ?? 0) }}</td>
                    <td>{{ number_format($row->final_price ?? ($row->count * $row->price)) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: left;">مبلغ کل نهایی:</th>
                <th>{{ number_format($invoice?->amount ?? $link->amount) }} ریال</th>
            </tr>
            @if($link->isPaid())
            <tr>
                <th colspan="4" style="text-align: left; color: green;">تسویه شده از درگاه آنلاین (کد پیگیری {{ $link->gateway_reference }}):</th>
                <th style="color: green;">{{ number_format($link->amount) }} ریال</th>
            </tr>
            @endif
        </tfoot>
    </table>

    <div class="footer">
        <div>مهر و امضای فروشنده</div>
        <div>مهر و امضای خریدار</div>
    </div>
</body>
</html>
