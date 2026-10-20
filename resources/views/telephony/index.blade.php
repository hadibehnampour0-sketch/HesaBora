<x-app-layout :title="__('Caller ID & VoIP Integration (Phase 19.2)')">
    <x-show-message-bags />

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold">{{ __('سیستم کالر آیدی (Caller ID) و تلفن ابری/سانترال VoIP') }}</h1>
                <p class="text-sm text-base-content/60">{{ __('شناسایی آنی هویت مشتریان هنگام زنگ خوردن تلفن، مشاهده مانده‌حساب، فاکتورهای باز، باشگاه مشتریان و ثبت سفارش سریع.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-success badge-outline gap-1 font-mono text-xs">
                    <span class="w-2 h-2 rounded-full bg-success animate-ping"></span>
                    {{ __('سرویس شنود وب‌هوک فعال') }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Test lookup & Pop-up simulator -->
            <div class="today-panel lg:col-span-1 space-y-4">
                <h3 class="text-base font-bold flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ __('شبیه‌ساز کالرآیدی و استعلام سریع') }}
                </h3>
                <p class="text-xs text-base-content/60">
                    {{ __('شماره تلفن مشتری (موبایل یا ثابت) را وارد کنید تا پرونده لحظه‌ای او واکشی شود:') }}
                </p>

                <form id="lookupForm" class="space-y-3">
                    <div>
                        <input type="text" id="phoneInput" name="number" placeholder="مثلاً: 09121234567 یا 02188888888" class="input input-bordered w-full text-left font-mono text-sm" required />
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-full">
                        {{ __('استعلام پرونده و تست پاپ‌آپ') }}
                    </button>
                </form>

                <div id="lookupResult" class="hidden border border-base-300 rounded-lg p-3 bg-base-200/50 space-y-2 text-xs">
                    <div class="font-bold text-sm text-base-content flex items-center justify-between">
                        <span id="resCustomerName">-</span>
                        <span id="resTier" class="badge badge-warning badge-sm">-</span>
                    </div>
                    <div class="flex justify-between text-base-content/70">
                        <span>{{ __('مانده‌حساب:') }}</span>
                        <span id="resBalance" class="font-bold font-mono">-</span>
                    </div>
                    <div class="flex justify-between text-base-content/70">
                        <span>{{ __('امتیاز وفاداری:') }}</span>
                        <span id="resPoints" class="font-mono text-primary font-bold">-</span>
                    </div>
                    <div class="flex justify-between text-base-content/70">
                        <span>{{ __('فاکتورهای باز تسویه‌نشده:') }}</span>
                        <span id="resOpenInvoices" class="font-mono text-error font-bold">-</span>
                    </div>
                </div>

                <div class="divider text-xs">{{ __('تنظیمات اتصال سانترال / VoIP') }}</div>
                <div class="text-xs text-base-content/70 space-y-2">
                    <p><strong>آدرس وب‌هوک سانترال:</strong></p>
                    <code class="block bg-base-300 p-2 rounded text-left font-mono select-all text-[11px] break-all">{{ route('telephony.webhook') }}</code>
                    <p class="text-[11px] text-base-content/50">
                        {{ __('سازگار با استریسک (Asterisk)، ایزابل (Issabel)، FreePBX و دستگاه‌های Caller ID سخت‌افزاری ۴ و ۸ خط متصل به USB.') }}
                    </p>
                </div>
            </div>

            <!-- Call Logs Table -->
            <div class="today-panel lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold">{{ __('تاریخچه آخرین تماس‌های ورودی') }}</h3>
                    <span class="text-xs text-base-content/50">{{ __('مجموع:') }} {{ $logs->total() }} تماس</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-sm w-full text-right">
                        <thead>
                            <tr class="bg-base-200">
                                <th>{{ __('وضعیت') }}</th>
                                <th>{{ __('شماره تماس') }}</th>
                                <th>{{ __('مشتری') }}</th>
                                <th>{{ __('مانده حساب') }}</th>
                                <th>{{ __('کانال') }}</th>
                                <th>{{ __('زمان') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                @php
                                    $snap = $log->customer_snapshot ?? [];
                                    $cust = $snap['customer'] ?? null;
                                @endphp
                                <tr class="hover">
                                    <td>
                                        <span class="badge badge-sm {{ match($log->call_status) {
                                            'ANSWERED' => 'badge-success',
                                            'MISSED' => 'badge-error',
                                            'RINGING' => 'badge-warning',
                                            default => 'badge-ghost',
                                        } }}">
                                            {{ match($log->call_status) {
                                                'ANSWERED' => 'پاسخ داده شد',
                                                'MISSED' => 'بی‌پاسخ',
                                                'RINGING' => 'در حال زنگ خوردن',
                                                default => $log->call_status,
                                            } }}
                                        </span>
                                    </td>
                                    <td class="font-mono text-xs font-semibold select-all" dir="ltr">
                                        {{ $log->caller_number }}
                                    </td>
                                    <td>
                                        @if($log->customer)
                                            <a href="{{ route('customers.show', $log->customer_id) }}" class="link link-hover font-bold text-primary">
                                                {{ $log->customer->name }}
                                            </a>
                                        @elseif($cust)
                                            <span class="font-bold">{{ $cust['name'] }}</span>
                                        @else
                                            <span class="text-base-content/40 italic">{{ __('ناشناس (جدید)') }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono text-xs">
                                        @if(isset($snap['balance']))
                                            <span class="{{ $snap['balance'] > 0 ? 'text-error font-semibold' : ($snap['balance'] < 0 ? 'text-success font-semibold' : 'text-base-content/50') }}">
                                                {{ number_format($snap['balance']) }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm text-[10px] font-mono">
                                            {{ $log->channel }}
                                        </span>
                                    </td>
                                    <td class="text-xs text-base-content/60 font-mono">
                                        {{ $log->created_at->format('H:i:s - Y/m/d') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-base-content/50">
                                        {{ __('هنوز تماسی ثبت نشده است.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('lookupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const number = document.getElementById('phoneInput').value;
            const resBox = document.getElementById('lookupResult');

            try {
                const response = await fetch(`{{ route('telephony.lookup') }}?number=${encodeURIComponent(number)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await response.json();

                if (data.success) {
                    resBox.classList.remove('hidden');
                    const profile = data.data;

                    if (profile.found) {
                        document.getElementById('resCustomerName').textContent = profile.customer.name;
                        document.getElementById('resTier').textContent = profile.loyalty.tier;
                        document.getElementById('resBalance').textContent = profile.balance.toLocaleString('fa-IR') + ' ریال';
                        document.getElementById('resPoints').textContent = profile.loyalty.points.toLocaleString('fa-IR') + ' امتیاز';
                        document.getElementById('resOpenInvoices').textContent = profile.open_invoices_count + ' فاکتور (' + profile.open_invoices_amount.toLocaleString('fa-IR') + ' ریال)';
                    } else {
                        document.getElementById('resCustomerName').textContent = 'مشتری با این شماره ثبت نشده است';
                        document.getElementById('resTier').textContent = 'جدید';
                        document.getElementById('resBalance').textContent = '۰ ریال';
                        document.getElementById('resPoints').textContent = '۰';
                        document.getElementById('resOpenInvoices').textContent = '۰';
                    }
                }
            } catch (err) {
                console.error(err);
                alert('خطا در برقراری ارتباط با سرور.');
            }
        });
    </script>
</x-app-layout>
