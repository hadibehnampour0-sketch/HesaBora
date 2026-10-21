<x-app-layout>
    <x-slot name="title">{{ __('اتصال مستقیم کارتخوان‌های فروشگاهی (PC-POS)') }}</x-slot>

    <div class="space-y-6" x-data="pcPosManager()">

        <!-- Breadcrumb & Top Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-base-100 p-5 rounded-2xl border border-base-300 shadow-sm">
            <div>
                <div class="flex items-center gap-2 text-xs text-base-content/60 mb-1">
                    <a href="/" class="hover:text-primary">{{ __('پیشخوان') }}</a>
                    <span>/</span>
                    <a href="{{ route('pos.terminal') }}" class="hover:text-primary">{{ __('صندوق فروشگاهی') }}</a>
                    <span>/</span>
                    <span class="text-base-content font-medium">{{ __('کارتخوان‌های بانکی متصل (PC-POS)') }}</span>
                </div>
                <h1 class="text-xl md:text-2xl font-black text-base-content flex items-center gap-2">
                    <span class="text-2xl">💳</span>
                    {{ __('مدیریت و اتصال مستقیم کارتخوان‌های بانکی (PC-POS)') }}
                </h1>
                <p class="text-xs text-base-content/70 mt-1">
                    {{ __('ارسال خودکار مبلغ فاکتور به کارتخوان، ثبت بی‌درنگ شماره پیگیری (RRN) و تسویه خودکار اسناد حسابداری بدون دخالت دست.') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        onclick="new_terminal_modal.showModal()"
                        class="btn btn-primary rounded-xl px-5 font-bold shadow-md shadow-primary/20 text-xs md:text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>{{ __('افزودن کارتخوان جدید') }}</span>
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success rounded-2xl shadow-sm text-sm text-white font-medium">
                <svg class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error rounded-2xl shadow-sm text-sm text-white font-medium">
                <svg class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Summary KPI Counters -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-xl font-black">
                    📟
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('کارتخوان‌های فعال') }}</div>
                    <div class="text-xl font-black text-base-content tabular-nums">{{ $terminals->where('is_active', true)->count() }} {{ __('دستگاه') }}</div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-success/10 text-success flex items-center justify-center text-xl font-black">
                    ⭐
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('کارتخوان پیش‌فرض') }}</div>
                    <div class="text-sm font-bold text-base-content truncate max-w-[150px]">
                        {{ $terminals->firstWhere('is_default', true)?->name ?? __('تعیین نشده') }}
                    </div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-accent/10 text-accent flex items-center justify-center text-xl font-black">
                    🧾
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('تراکنش‌های موفق') }}</div>
                    <div class="text-xl font-black text-base-content tabular-nums">
                        {{ $transactions->where('status', 'success')->count() }} {{ __('مورد') }}
                    </div>
                </div>
            </div>

            <div class="bg-base-100 p-4 rounded-2xl border border-base-300 shadow-sm flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-info/10 text-info flex items-center justify-center text-xl font-black">
                    💰
                </div>
                <div>
                    <div class="text-xs text-base-content/60">{{ __('مجموع تسویه با کارتخوان') }}</div>
                    <div class="text-lg font-black text-base-content tabular-nums">
                        {{ number_format($transactions->where('status', 'success')->sum('amount') / 10) }}
                        <span class="text-[10px] text-base-content/60">{{ __('تومان') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terminals Grid -->
        <div class="bg-base-100 p-6 rounded-2xl border border-base-300 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-base-content flex items-center gap-2">
                    <span>📡</span>
                    {{ __('پایانه‌های کارتخوان تعریف‌شده') }}
                </h2>
                <span class="text-xs text-base-content/60">{{ __('پشتیبانی از پروتکل‌های به‌پرداخت، سامان‌کیش، سداد، ایران‌کیش، آسان‌پرداخت و شبیه‌ساز') }}</span>
            </div>

            @if ($terminals->isEmpty())
                <div class="text-center py-12 border-2 border-dashed border-base-300 rounded-2xl">
                    <div class="text-4xl mb-3">💳</div>
                    <h3 class="text-sm font-bold text-base-content mb-1">{{ __('هنوز هیچ کارتخوانی به سیستم متصل نشده است') }}</h3>
                    <p class="text-xs text-base-content/60 mb-4 max-w-md mx-auto">
                        {{ __('با افزودن کارتخوان، صندوق‌دار می‌تواند مبلغ فاکتور را با یک کلیک به دستگاه بفرستد و تسویه حسابداری را خودکار انجام دهد.') }}
                    </p>
                    <button type="button" onclick="new_terminal_modal.showModal()" class="btn btn-primary btn-sm rounded-xl">
                        {{ __('افزودن اولین کارتخوان') }}
                    </button>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($terminals as $terminal)
                        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4 hover:border-primary/50 transition relative">
                            @if ($terminal->is_default)
                                <span class="badge badge-primary badge-xs py-1.5 px-2 font-bold absolute top-3 left-3 text-[10px]">
                                    {{ __('کارتخوان پیش‌فرض') }}
                                </span>
                            @endif

                            <div class="flex items-start gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl bg-base-100 border border-base-300 flex items-center justify-center text-lg shadow-sm">
                                    @if ($terminal->psp === 'simulator')
                                        🧪
                                    @else
                                        💳
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 pr-1">
                                    <h4 class="font-bold text-sm text-base-content truncate">{{ $terminal->name }}</h4>
                                    <div class="text-xs text-base-content/60 flex items-center gap-1.5 mt-0.5">
                                        <span class="badge badge-ghost badge-xs">{{ $terminal->psp_label }}</span>
                                        <span>•</span>
                                        <span>{{ $terminal->connection_type_label }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1.5 text-xs text-base-content/80 bg-base-100 p-2.5 rounded-xl border border-base-200 font-mono mb-3">
                                @if ($terminal->connection_type === 'lan')
                                    <div class="flex justify-between">
                                        <span class="text-base-content/60">{{ __('آدرس شبکه:') }}</span>
                                        <span class="font-semibold">{{ $terminal->ip_address }}:{{ $terminal->port }}</span>
                                    </div>
                                @elseif ($terminal->connection_type === 'serial')
                                    <div class="flex justify-between">
                                        <span class="text-base-content/60">{{ __('پورت سریال:') }}</span>
                                        <span class="font-semibold">{{ $terminal->serial_port ?? 'COM1' }} ({{ $terminal->baud_rate }})</span>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">{{ __('شماره ترمینال:') }}</span>
                                    <span>{{ $terminal->terminal_id ?: '---' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-base-content/60">{{ __('حساب معین بانکی:') }}</span>
                                    <span class="font-sans text-[11px] truncate max-w-[140px] text-primary">
                                        {{ $terminal->bankAccountSubject?->name ?? __('حساب پیش‌فرض سیستم') }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $terminal->last_status === 'online' ? 'bg-success animate-pulse' : ($terminal->last_status === 'offline' ? 'bg-error' : 'bg-base-content/30') }}"></span>
                                    <span class="text-[11px] font-medium text-base-content/70">
                                        {{ $terminal->last_status === 'online' ? __('آنلاین') : ($terminal->last_status === 'offline' ? __('آفلاین') : __('نامشخص')) }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button type="button"
                                            @click="pingTerminal({{ $terminal->id }})"
                                            :disabled="pingingId === {{ $terminal->id }}"
                                            class="btn btn-ghost btn-xs rounded-lg text-primary"
                                            title="{{ __('تست اتصال و پینگ') }}">
                                        <span x-show="pingingId !== {{ $terminal->id }}">📡 {{ __('تست پینگ') }}</span>
                                        <span x-show="pingingId === {{ $terminal->id }}" class="loading loading-spinner loading-xs"></span>
                                    </button>

                                    <form method="POST" action="{{ route('pc-pos.destroy', $terminal) }}" onsubmit="return confirm('{{ __('آیا از حذف این کارتخوان اطمینان دارید؟') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-xs text-error rounded-lg" title="{{ __('حذف') }}">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Transactions Audit Log Table -->
        <div class="bg-base-100 p-6 rounded-2xl border border-base-300 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-base-content flex items-center gap-2">
                        <span>📋</span>
                        {{ __('تاریخچه تراکنش‌های بانکی کارتخوان‌ها') }}
                    </h2>
                    <p class="text-xs text-base-content/60 mt-0.5">
                        {{ __('تمام پرداخت‌های ثبت‌شده توسط کارتخوان به همراه شماره مرجع بانکی (RRN) و سند تسویه') }}
                    </p>
                </div>
            </div>

            @if ($transactions->isEmpty())
                <div class="text-center py-8 text-base-content/50 text-xs">
                    {{ __('هنوز تراکنشی از طریق کارتخوان ثبت نشده است.') }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra table-sm w-full">
                        <thead>
                            <tr class="text-xs text-base-content/70">
                                <th>#</th>
                                <th>{{ __('زمان تراکنش') }}</th>
                                <th>{{ __('کارتخوان') }}</th>
                                <th>{{ __('فاکتور') }}</th>
                                <th>{{ __('مبلغ (تومان)') }}</th>
                                <th>{{ __('شماره مرجع (RRN)') }}</th>
                                <th>{{ __('شماره کارت') }}</th>
                                <th>{{ __('کاربر') }}</th>
                                <th>{{ __('وضعیت') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $tx)
                                <tr class="text-xs">
                                    <td class="font-mono">{{ $tx->id }}</td>
                                    <td class="text-base-content/70">{{ $tx->transaction_at ? convertToJalali($tx->transaction_at, true) : '---' }}</td>
                                    <td class="font-bold">{{ $tx->terminal?->name ?? '---' }}</td>
                                    <td>
                                        @if ($tx->invoice)
                                            <a href="{{ route('invoices.show', $tx->invoice) }}" class="link link-primary font-bold">
                                                #{{ $tx->invoice->number }}
                                            </a>
                                        @else
                                            <span class="text-base-content/40">{{ __('آزاد') }}</span>
                                        @endif
                                    </td>
                                    <td class="font-mono font-bold text-base-content">
                                        {{ number_format($tx->amount / 10) }}
                                    </td>
                                    <td class="font-mono text-primary font-bold">{{ $tx->rrn ?: ($tx->terminal_trace_no ?: '---') }}</td>
                                    <td class="font-mono text-base-content/70">{{ $tx->card_number_masked ?: '---' }}</td>
                                    <td>{{ $tx->user?->name ?? '---' }}</td>
                                    <td>
                                        <span class="badge {{ $tx->status_badge_class }} badge-xs py-1 px-1.5 font-bold">
                                            {{ $tx->status_label }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>

        <!-- Add New Terminal Modal -->
        <dialog id="new_terminal_modal" class="modal">
            <div class="modal-box max-w-xl rounded-3xl p-6">
                <form method="dialog">
                    <button class="btn btn-sm btn-circle btn-ghost absolute right-4 top-4">✕</button>
                </form>

                <h3 class="text-lg font-black text-base-content flex items-center gap-2 mb-4">
                    <span>📟</span>
                    {{ __('افزودن کارتخوان جدید (PC-POS)') }}
                </h3>

                <form method="POST" action="{{ route('pc-pos.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="label"><span class="label-text text-xs font-bold">{{ __('نام / برچسب کارتخوان') }}</span></label>
                        <input type="text" name="name" required placeholder="مثلاً: کارتخوان صندوق ۱ - به‌پرداخت" class="input input-bordered input-sm w-full rounded-xl" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('شرکت پرداخت (PSP)') }}</span></label>
                            <select name="psp" class="select select-bordered select-sm w-full rounded-xl" x-model="selectedPsp">
                                <option value="behpardakht">{{ __('به‌پرداخت ملت') }}</option>
                                <option value="saman_kish">{{ __('سامان‌کیش (SEP)') }}</option>
                                <option value="sadad">{{ __('سداد بانک ملی') }}</option>
                                <option value="iran_kish">{{ __('ایران‌کیش') }}</option>
                                <option value="asan_pardakht">{{ __('آسان‌پرداخت (آپ)') }}</option>
                                <option value="pasargad">{{ __('فناپ / پاسارگاد') }}</option>
                                <option value="simulator">{{ __('شبیه‌ساز تستی (دمو بدون دستگاه)') }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('نوع اتصال') }}</span></label>
                            <select name="connection_type" class="select select-bordered select-sm w-full rounded-xl" x-model="connectionType">
                                <option value="lan">{{ __('شبکه محلی (LAN / IP)') }}</option>
                                <option value="serial">{{ __('کابل سریال / USB (COM)') }}</option>
                                <option value="bridge">{{ __('ایجنت سخت‌افزاری لوکال') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="connectionType === 'lan'">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('آدرس IP کارتخوان') }}</span></label>
                            <input type="text" name="ip_address" value="192.168.1.150" class="input input-bordered input-sm w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('پورت شبکه') }}</span></label>
                            <input type="number" name="port" value="8888" class="input input-bordered input-sm w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="connectionType === 'serial'">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('پورت سریال (COM Port)') }}</span></label>
                            <input type="text" name="serial_port" value="COM3" class="input input-bordered input-sm w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('Baud Rate') }}</span></label>
                            <input type="number" name="baud_rate" value="115200" class="input input-bordered input-sm w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('شماره ترمینال (Terminal ID)') }}</span></label>
                            <input type="text" name="terminal_id" placeholder="اختیاری" class="input input-bordered input-sm w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text text-xs font-bold">{{ __('شماره پذیرنده (Merchant ID)') }}</span></label>
                            <input type="text" name="merchant_id" placeholder="اختیاری" class="input input-bordered input-sm w-full rounded-xl font-mono text-left" dir="ltr" />
                        </div>
                    </div>

                    <div>
                        <label class="label"><span class="label-text text-xs font-bold">{{ __('حساب معین بانکی تسویه (کدینگ حسابداری)') }}</span></label>
                        <select name="bank_account_subject_id" class="select select-bordered select-sm w-full rounded-xl">
                            <option value="">{{ __('انتخاب خودکار بر اساس حساب پیش‌فرض شرکت') }}</option>
                            @foreach ($bankSubjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }} ({{ $subject->formattedCode() }})</option>
                            @endforeach
                        </select>
                        <span class="text-[10px] text-base-content/60 mt-1 block">
                            {{ __('تراکنش‌های این کارتخوان مستقیماً در سند حسابداری به عنوان بدهکار این حساب معین بانکی ثبت می‌شوند.') }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between p-3 bg-base-200/50 rounded-xl">
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="is_default" value="1" class="checkbox checkbox-primary checkbox-sm rounded-lg" />
                            <span class="label-text text-xs font-bold">{{ __('تنظیم به عنوان کارتخوان پیش‌فرض فروشگاه') }}</span>
                        </label>
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="is_active" value="1" checked class="checkbox checkbox-success checkbox-sm rounded-lg" />
                            <span class="label-text text-xs font-bold">{{ __('فعال') }}</span>
                        </label>
                    </div>

                    <div class="modal-action">
                        <button type="submit" class="btn btn-primary btn-sm rounded-xl px-5">
                            {{ __('ذخیره و اتصال کارتخوان') }}
                        </button>
                    </div>
                </form>
            </div>
        </dialog>

    </div>

    <script>
        function pcPosManager() {
            return {
                selectedPsp: 'behpardakht',
                connectionType: 'lan',
                pingingId: null,

                async pingTerminal(terminalId) {
                    this.pingingId = terminalId;
                    try {
                        const res = await fetch(`{{ url('pc-pos') }}/${terminalId}/ping`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await res.json();
                        alert(data.message);
                        if (data.success) {
                            window.location.reload();
                        }
                    } catch (e) {
                        alert('خطا در برقراری ارتباط با سرور.');
                    } finally {
                        this.pingingId = null;
                    }
                }
            };
        }
    </script>
</x-app-layout>
