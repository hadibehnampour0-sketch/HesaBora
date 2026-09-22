<!DOCTYPE html>
<html dir="rtl" lang="fa">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: right; }
        th { background-color: #f0f0f0; }
        .disclaimer { margin-top: 16px; font-size: 10px; color: #666; }
    </style>
</head>

<body>
    <h1>{{ __('Seasonal Tax Report') }} -- {{ __('Quarter') }} {{ $report['quarter'] }} ({{ $report['quarter_label'] }}) {{ $report['jalali_year'] }}</h1>
    <p>{{ __('Date Range') }}: {{ $report['start_date'] }} - {{ $report['end_date'] }}</p>

    <table>
        <tbody>
            <tr><th>{{ __('Taxable Sales') }}</th><td>{{ localizeNumber(number_format($report['taxable_sales'], 2)) }}</td></tr>
            <tr><th>{{ __('Exempt Sales') }}</th><td>{{ localizeNumber(number_format($report['exempt_sales'], 2)) }}</td></tr>
            <tr><th>{{ __('VAT Collected') }}</th><td>{{ localizeNumber(number_format($report['vat_collected'], 2)) }}</td></tr>
            <tr><th>{{ __('Taxable Purchases') }}</th><td>{{ localizeNumber(number_format($report['taxable_purchases'], 2)) }}</td></tr>
            <tr><th>{{ __('Creditable Input VAT') }}</th><td>{{ localizeNumber(number_format($report['creditable_input_vat'], 2)) }}</td></tr>
            <tr><th>{{ __('Net VAT Payable') }}</th><td>{{ localizeNumber(number_format($report['net_vat_payable'], 2)) }}</td></tr>
        </tbody>
    </table>

    <p class="disclaimer">{{ __('This report is a reasonable, invoice-level approximation of the Article 169 seasonal VAT return. It has not been verified against the exact byte-level TTMS filing format.') }}</p>
</body>

</html>
