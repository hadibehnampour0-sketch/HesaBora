<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $template->header_config['custom_title'] ?? 'صورتحساب فروش' }} - {{ $invoice->number }}</title>
    <style>
        @page {
            @if($template->paper_size === 'A4_LANDSCAPE' || $template->paper_size === 'A5_LANDSCAPE')
                size: {{ str_starts_with($template->paper_size, 'A5') ? 'A5 landscape' : 'A4 landscape' }};
            @elseif(str_starts_with($template->paper_size, 'THERMAL'))
                size: {{ $template->paper_size === 'THERMAL_58MM' ? '58mm auto' : '80mm auto' }};
                margin: 2mm;
            @else
                size: {{ str_starts_with($template->paper_size, 'A5') ? 'A5 portrait' : 'A4 portrait' }};
                margin: 10mm;
            @endif
        }

        body {
            font-family: Tahoma, 'Vazirmatn', sans-serif;
            font-size: {{ ($template->header_config['font_size_pt'] ?? 10) }}pt;
            direction: rtl;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
            position: relative;
        }

        .watermark {
            position: absolute;
            top: 40%;
            left: 20%;
            font-size: 55pt;
            color: rgba(200, 200, 200, 0.25);
            transform: rotate(-30deg);
            z-index: -1;
            pointer-events: none;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #333;
            padding: 5px 6px;
            text-align: center;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .header-box {
            border: 1px solid #333;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 10px;
        }

        .party-box {
            border: 1px solid #333;
            padding: 6px;
            border-radius: 4px;
            font-size: 8.5pt;
            line-height: 1.5;
        }

        .party-title {
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            padding-bottom: 2px;
            margin-bottom: 4px;
            background: #fafafa;
        }

        .footer-signatures {
            margin-top: 25px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }

        .sig-box {
            border: 1px dashed #666;
            padding: 10px;
            width: 35%;
            height: 60px;
        }

        .legal-notice {
            margin-top: 15px;
            font-size: 8pt;
            color: #444;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        .no-print {
            background: #eee;
            padding: 10px;
            margin-bottom: 15px;
            text-align: center;
            border-bottom: 1px solid #ccc;
        }

        @media print {
            .no-print {
                display: none;
            }
        }

        {!! $template->custom_css ?? '' !!}
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 6px 15px; font-weight: bold; cursor: pointer;">چاپ فاکتور</button>
        <span style="margin: 0 10px;">|</span>
        <span>قالب: <strong>{{ $template->name }}</strong> ({{ $template->paper_size }})</span>
    </div>

    @if($template->watermark_text)
        <div class="watermark">{{ $template->watermark_text }}</div>
    @endif

    <!-- Header Box -->
    <div class="header-box">
        <table style="border: none; margin: 0;">
            <tr style="border: none;">
                <td style="border: none; text-align: right; width: 33%;">
                    @if($template->header_config['show_company_name'] ?? true)
                        <div style="font-weight: bold; font-size: 12pt;">{{ $company?->name ?? 'فروشگاه و شرکت دخل' }}</div>
                    @endif
                    @if($template->header_config['show_economic_code'] ?? true)
                        <div style="font-size: 8.5pt;">کد اقتصادی: {{ $company?->economic_code ?? '-' }}</div>
                    @endif
                    @if($template->header_config['show_national_code'] ?? true)
                        <div style="font-size: 8.5pt;">شناسه ملی: {{ $company?->national_id ?? '-' }}</div>
                    @endif
                </td>
                <td style="border: none; text-align: center; width: 34%;">
                    <h2 style="margin: 0; font-size: 14pt;">{{ $template->header_config['custom_title'] ?? 'صورتحساب فروش' }}</h2>
                </td>
                <td style="border: none; text-align: left; width: 33%;">
                    <div style="font-size: 9pt;">شماره فاکتور: <strong>{{ $invoice->number }}</strong></div>
                    <div style="font-size: 9pt;">تاریخ: <strong>{{ $invoice->date?->format('Y/m/d') ?? date('Y/m/d') }}</strong></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Parties Grid (Buyer / Seller) -->
    <div class="parties-grid">
        <div class="party-box">
            <div class="party-title">مشخصات خریدار</div>
            <div>نام شخص / شرکت: <strong>{{ $invoice->customer?->name ?? 'مشتری آزاد' }}</strong></div>
            <div>شناسه ملی / کد ملی: {{ $invoice->customer?->personal_code ?? '-' }}</div>
            <div>تلفن تماس: {{ $invoice->customer?->phone ?: ($invoice->customer?->mobile ?: '-') }}</div>
            <div>نشانی: {{ $invoice->customer?->address ?? '-' }}</div>
        </div>
        <div class="party-box">
            <div class="party-title">مشخصات فروشنده</div>
            <div>نام فروشنده: <strong>{{ $company?->name ?? 'دخل' }}</strong></div>
            <div>تلفن: {{ $company?->phone ?? '-' }}</div>
            <div>نشانی: {{ $company?->address ?? '-' }}</div>
        </div>
    </div>

    <!-- Items Table -->
    <table>
        <thead>
            <tr>
                @foreach($template->columns_config ?? [] as $col)
                    @if($col['visible'] ?? true)
                        <th style="width: {{ $col['width_percent'] ?? 10 }}%;">{{ $col['label'] }}</th>
                    @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $items = $invoice->items()->get(); @endphp
            @forelse($items as $idx => $item)
                <tr>
                    @foreach($template->columns_config ?? [] as $col)
                        @if($col['visible'] ?? true)
                            <td>
                                @switch($col['key'])
                                    @case('row_num')
                                        {{ $idx + 1 }}
                                        @break
                                    @case('code')
                                        {{ $item->itemable?->code ?? '-' }}
                                        @break
                                    @case('description')
                                        {{ $item->itemable?->name ?? $item->description ?? '-' }}
                                        @break
                                    @case('quantity')
                                        {{ number_format($item->quantity) }}
                                        @break
                                    @case('unit_price')
                                        {{ number_format($item->unit_price) }}
                                        @break
                                    @case('discount')
                                        {{ number_format($item->unit_discount) }}
                                        @break
                                    @case('vat')
                                        {{ number_format($item->vat) }}
                                        @break
                                    @case('total_amount')
                                        {{ number_format($item->amount) }}
                                        @break
                                    @default
                                        -
                                @endswitch
                            </td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    @php $visCols = count(array_filter($template->columns_config ?? [], fn($c) => $c['visible'] ?? true)); @endphp
                    <td colspan="{{ max(1, $visCols) }}">هیچ ردیف کالایی ثبت نشده است.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background: #fafafa; font-weight: bold;">
                <td colspan="4" style="text-align: left; padding-left: 10px;">مجموع قابل پرداخت:</td>
                <td colspan="4" style="text-align: right; font-size: 11pt;">{{ number_format($invoice->amount) }} ریال</td>
            </tr>
        </tfoot>
    </table>

    <!-- Words Amount -->
    @if($template->footer_config['show_words_amount'] ?? true)
        <div style="margin-top: 8px; font-size: 8.5pt;">
            مبلغ کل به حروف: <strong>{{ number_format($invoice->amount) }} ریال</strong>
        </div>
    @endif

    <!-- Bank Details -->
    @if($template->footer_config['show_bank_sheba'] ?? true)
        <div style="margin-top: 8px; padding: 6px; border: 1px dotted #888; font-size: 8pt; border-radius: 4px;">
            اطلاعات واریز: شماره حساب و شبا متعلق به <strong>{{ $company?->name }}</strong>
        </div>
    @endif

    <!-- Legal Note -->
    @if(! empty($template->footer_config['legal_notice']))
        <div class="legal-notice">
            {{ $template->footer_config['legal_notice'] }}
        </div>
    @endif

    <!-- Signatures -->
    @if($template->footer_config['show_signatures'] ?? true)
        <div class="footer-signatures">
            <div class="sig-box">
                {{ $template->footer_config['signature_labels'][0] ?? 'امضاء و مهر خریدار' }}
            </div>
            <div class="sig-box">
                {{ $template->footer_config['signature_labels'][1] ?? 'امضاء و مهر فروشنده' }}
            </div>
        </div>
    @endif
</body>
</html>
