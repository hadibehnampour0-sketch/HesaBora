@props(['name' => 'dot'])

@php
    $paths = [
        'home' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
        'portal' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0',
        'invoices' => 'M7 3h8l4 4v14H7V3Zm8 0v4h4M10 12h6M10 16h6',
        'accounting' => 'M3 5h18v14H3V5Zm0 5h18M8 15h3',
        'reports' => 'M4 19V9m5 10V5m5 14v-7m5 7V3',
        'warehouse' => 'M3 9l9-6 9 6v11H3V9Zm6 11v-6h6v6',
        'hr' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        'field' => 'M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        'crm' => 'M4 4h16v12H8l-4 4V4Z',
        'industry' => 'M3 21h18M5 21V8l5 3V8l5 3V4h4v17',
        'management' => 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19 12h2M3 12h2m7-9v2m0 14v2m6.36-2.64-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.42 1.42M7.05 16.95l-1.41 1.41',
        'dot' => 'M12 12h.01',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'h-[18px] w-[18px] shrink-0']) }}>
    <path d="{{ $paths[$name] ?? $paths['dot'] }}" />
</svg>
