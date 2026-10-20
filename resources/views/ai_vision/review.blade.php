<x-app-layout :title="__('Review & Verify AI Extracted Invoice #' . $extraction->id)">
    <x-show-message-bags />

    <div class="mx-auto max-w-7xl space-y-4">
        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-2 border-b border-base-200">
            <div>
                <a href="{{ route('ai-invoices.index') }}" class="btn btn-ghost btn-xs mb-1">
                    &larr; {{ __('بازگشت به صف فاکتورها') }}
                </a>
                <h1 class="text-lg font-bold flex items-center gap-2">
                    {{ __('بازبینی و تطبیق فاکتور استخراج شده') }} #{{ $extraction->id }}
                    <span class="badge badge-sm {{ $extraction->confidence_score >= 0.85 ? 'badge-success' : 'badge-warning' }}">
                        دقت هوش مصنوعی: {{ round($extraction->confidence_score * 100) }}%
                    </span>
                    <span class="badge badge-sm badge-outline">{{ $extraction->status }}</span>
                </h1>
            </div>

            <div class="flex items-center gap-2">
                @if($extraction->status !== 'CONVERTED')
                    <!-- Reject Form -->
                    <form action="{{ route('ai-invoices.reject', $extraction->id) }}" method="POST" onsubmit="return confirm('آیا از رد این فاکتور اطمینان دارید؟');">
                        @csrf
                        <button type="submit" class="btn btn-error btn-outline btn-sm">
                            {{ __('رد سند') }}
                        </button>
                    </form>

                    <!-- Convert Form -->
                    <form action="{{ route('ai-invoices.convert', $extraction->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $extraction->matched_customer_id }}">
                        <button type="submit" class="btn btn-success btn-sm gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('تأیید و صدور فاکتور خرید در دخل') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('invoices.show', $extraction->converted_invoice_id) }}" class="btn btn-primary btn-sm">
                        {{ __('مشاهده فاکتور خرید صادر شده #' . ($extraction->convertedInvoice?->number ?? '')) }}
                    </a>
                @endif
            </div>
        </div>

        <!-- 2-Column Split Workspace -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Side: Scanned Document Viewer (5 cols) -->
            <div class="lg:col-span-5 today-panel sticky top-4 space-y-3">
                <div class="flex items-center justify-between text-xs font-semibold text-base-content/70">
                    <span>{{ __('سند کاغذی بارگذاری شده:') }} {{ $extraction->original_filename }}</span>
                    <a href="{{ $fileUrl }}" target="_blank" class="link link-primary">{{ __('مشاهده اندازه اصلی') }}</a>
                </div>

                <div class="border border-base-300 rounded-lg overflow-hidden bg-base-300/30 flex items-center justify-center max-h-[75vh]">
                    @if(str_contains($extraction->file_type, 'pdf'))
                        <iframe src="{{ $fileUrl }}" class="w-full h-[650px] border-0"></iframe>
                    @else
                        <img src="{{ $fileUrl }}" alt="Scanned Invoice" class="w-full h-auto max-h-[70vh] object-contain cursor-zoom-in" onclick="window.open('{{ $fileUrl }}', '_blank')">
                    @endif
                </div>
            </div>

            <!-- Right Side: Extracted Editable Fields & Items (7 cols) -->
            <div class="lg:col-span-7 space-y-4">
                <form action="{{ route('ai-invoices.update', $extraction->id) }}" method="POST" id="updateExtractionForm">
                    @csrf
                    @method('PUT')

                    <!-- Vendor & Match Card -->
                    <div class="today-panel space-y-4">
                        <h3 class="text-sm font-bold border-b pb-2 flex items-center justify-between">
                            <span>{{ __('۱. اطلاعات فروشنده / تأمین‌کننده') }}</span>
                            @if($extraction->matchedCustomer)
                                <span class="badge badge-success badge-sm font-normal">{{ __('تطبیق با طرف‌حساب موجود') }}</span>
                            @else
                                <span class="badge badge-warning badge-sm font-normal">{{ __('نیاز به تأیید طرف‌حساب') }}</span>
                            @endif
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <label class="label-text font-semibold">{{ __('نام تأمین‌کننده در فاکتور') }}</label>
                                <input type="text" name="vendor_name" value="{{ old('vendor_name', $extraction->vendor_name) }}" class="input input-bordered input-sm w-full mt-1">
                            </div>
                            <div>
                                <label class="label-text font-semibold">{{ __('شماره تماس / موبایل') }}</label>
                                <input type="text" name="vendor_phone" value="{{ old('vendor_phone', $extraction->vendor_phone) }}" class="input input-bordered input-sm w-full mt-1 text-left font-mono">
                            </div>
                            <div>
                                <label class="label-text font-semibold">{{ __('شناسه ملی / کد اقتصادی') }}</label>
                                <input type="text" name="vendor_national_code" value="{{ old('vendor_national_code', $extraction->vendor_national_code) }}" class="input input-bordered input-sm w-full mt-1 text-left font-mono">
                            </div>
                            <div>
                                <label class="label-text font-semibold text-primary">{{ __('انتخاب طرف‌حساب در دخل:') }}</label>
                                <select name="matched_customer_id" class="select select-bordered select-sm w-full mt-1">
                                    <option value="">{{ __('+ ایجاد خودکار طرف‌حساب جدید از روی نام بالا') }}</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" {{ (string)$extraction->matched_customer_id === (string)$c->id ? 'selected' : '' }}>
                                            {{ $c->name }} ({{ $c->mobile ?: $c->phone ?: 'بدون تلفن' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-2 border-t">
                            <div>
                                <label class="label-text font-semibold">{{ __('شماره فاکتور کاغذی') }}</label>
                                <input type="text" name="invoice_number" value="{{ old('invoice_number', $extraction->invoice_number) }}" class="input input-bordered input-sm w-full mt-1 font-mono">
                            </div>
                            <div>
                                <label class="label-text font-semibold">{{ __('تاریخ فاکتور') }}</label>
                                <input type="text" name="invoice_date" value="{{ old('invoice_date', $extraction->invoice_date) }}" class="input input-bordered input-sm w-full mt-1 font-mono">
                            </div>
                        </div>
                    </div>

                    <!-- Items Card -->
                    <div class="today-panel space-y-4">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h3 class="text-sm font-bold">{{ __('۲. اقلام استخراج شده از فاکتور') }}</h3>
                            <button type="button" class="btn btn-ghost btn-xs text-primary" onclick="addNewRow()">
                                {{ __('+ افزودن سطر کالا') }}
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="table table-xs w-full text-right" id="itemsTable">
                                <thead>
                                    <tr class="bg-base-200">
                                        <th style="width: 35%;">{{ __('شرح کالا / خدمات') }}</th>
                                        <th style="width: 15%;">{{ __('تعداد') }}</th>
                                        <th style="width: 25%;">{{ __('قیمت واحد (ریال)') }}</th>
                                        <th style="width: 20%;">{{ __('مبلغ کل') }}</th>
                                        <th style="width: 5%;"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTbody">
                                    @php
                                        $items = $extraction->raw_extracted_data['items'] ?? [];
                                    @endphp
                                    @forelse($items as $i => $item)
                                        <tr class="item-row">
                                            <td>
                                                <input type="text" name="items[{{ $i }}][name]" value="{{ $item['name'] ?? '' }}" class="input input-bordered input-xs w-full" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" class="input input-bordered input-xs w-full font-mono item-qty" onchange="recalcRow(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" step="1" min="0" name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" class="input input-bordered input-xs w-full font-mono item-unit" onchange="recalcRow(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" step="1" min="0" name="items[{{ $i }}][total_price]" value="{{ $item['total_price'] ?? 0 }}" class="input input-bordered input-xs w-full font-mono item-total" readonly>
                                                <input type="hidden" name="items[{{ $i }}][vat_amount]" value="{{ $item['vat_amount'] ?? 0 }}" class="item-vat">
                                                <input type="hidden" name="items[{{ $i }}][discount]" value="{{ $item['discount'] ?? 0 }}">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-ghost btn-xs text-error" onclick="removeRow(this)">✕</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="item-row">
                                            <td>
                                                <input type="text" name="items[0][name]" value="کالای نمونه ۱" class="input input-bordered input-xs w-full" required>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0.01" name="items[0][quantity]" value="1" class="input input-bordered input-xs w-full font-mono item-qty" onchange="recalcRow(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" step="1" min="0" name="items[0][unit_price]" value="{{ $extraction->subtotal }}" class="input input-bordered input-xs w-full font-mono item-unit" onchange="recalcRow(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" step="1" min="0" name="items[0][total_price]" value="{{ $extraction->subtotal }}" class="input input-bordered input-xs w-full font-mono item-total" readonly>
                                                <input type="hidden" name="items[0][vat_amount]" value="{{ $extraction->vat_amount }}" class="item-vat">
                                                <input type="hidden" name="items[0][discount]" value="0">
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-ghost btn-xs text-error" onclick="removeRow(this)">✕</button>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals Section -->
                        <div class="bg-base-200/50 p-3 rounded-lg space-y-2 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-base-content/70">{{ __('جمع اقلام (بدون مالیات):') }}</span>
                                <input type="number" name="subtotal" id="formSubtotal" value="{{ old('subtotal', $extraction->subtotal) }}" class="input input-bordered input-xs font-mono text-left w-36" onchange="recalcGrandTotal()">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-base-content/70">{{ __('مالیات بر ارزش افزوده (VAT):') }}</span>
                                <input type="number" name="vat_amount" id="formVat" value="{{ old('vat_amount', $extraction->vat_amount) }}" class="input input-bordered input-xs font-mono text-left w-36" onchange="recalcGrandTotal()">
                            </div>
                            <div class="divider my-1"></div>
                            <div class="flex justify-between items-center font-bold text-sm">
                                <span>{{ __('مبلغ قابل پرداخت کل:') }}</span>
                                <input type="number" name="total_amount" id="formTotal" value="{{ old('total_amount', $extraction->total_amount) }}" class="input input-bordered input-xs font-mono text-left font-bold text-primary w-36" readonly>
                            </div>
                        </div>

                        @if($extraction->status !== 'CONVERTED')
                            <div class="flex justify-end pt-2">
                                <button type="submit" class="btn btn-neutral btn-sm">
                                    {{ __('ذخیره تغییرات پیش‌نویس') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function recalcRow(input) {
            const row = input.closest('tr');
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const unit = parseFloat(row.querySelector('.item-unit').value) || 0;
            const total = Math.round(qty * unit);
            row.querySelector('.item-total').value = total;
            row.querySelector('.item-vat').value = Math.round(total * 0.10);

            recalculateAll();
        }

        function recalculateAll() {
            let subtotal = 0;
            let vat = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                subtotal += parseFloat(row.querySelector('.item-total').value) || 0;
                vat += parseFloat(row.querySelector('.item-vat').value) || 0;
            });

            document.getElementById('formSubtotal').value = subtotal;
            document.getElementById('formVat').value = vat;
            document.getElementById('formTotal').value = subtotal + vat;
        }

        function recalcGrandTotal() {
            const subtotal = parseFloat(document.getElementById('formSubtotal').value) || 0;
            const vat = parseFloat(document.getElementById('formVat').value) || 0;
            document.getElementById('formTotal').value = subtotal + vat;
        }

        function removeRow(btn) {
            const rows = document.querySelectorAll('.item-row');
            if (rows.length > 1) {
                btn.closest('tr').remove();
                recalculateAll();
            } else {
                alert('حداقل یک سطر کالا در فاکتور الزامی است.');
            }
        }

        function addNewRow() {
            const tbody = document.getElementById('itemsTbody');
            const index = document.querySelectorAll('.item-row').length;
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            tr.innerHTML = `
                <td>
                    <input type="text" name="items[${index}][name]" value="کالای جدید ${index + 1}" class="input input-bordered input-xs w-full" required>
                </td>
                <td>
                    <input type="number" step="0.01" min="0.01" name="items[${index}][quantity]" value="1" class="input input-bordered input-xs w-full font-mono item-qty" onchange="recalcRow(this)" required>
                </td>
                <td>
                    <input type="number" step="1" min="0" name="items[${index}][unit_price]" value="0" class="input input-bordered input-xs w-full font-mono item-unit" onchange="recalcRow(this)" required>
                </td>
                <td>
                    <input type="number" step="1" min="0" name="items[${index}][total_price]" value="0" class="input input-bordered input-xs w-full font-mono item-total" readonly>
                    <input type="hidden" name="items[${index}][vat_amount]" value="0" class="item-vat">
                    <input type="hidden" name="items[${index}][discount]" value="0">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-ghost btn-xs text-error" onclick="removeRow(this)">✕</button>
                </td>
            `;
            tbody.appendChild(tr);
        }
    </script>
</x-app-layout>
