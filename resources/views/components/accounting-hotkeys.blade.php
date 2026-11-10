<div x-data="accountingHotkeys()"
     x-init="init()"
     @keydown.window="handleGlobalKeydown($event)"
     class="relative z-50">

    <!-- Hotkey Visual Cheat-Sheet Modal (F1 / Shift+?) -->
    <div x-show="showCheatSheet"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
         @click.self="showCheatSheet = false"
         style="display: none;">

        <div class="bg-base-100 border border-base-300 rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[85vh]">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-base-200 flex items-center justify-between bg-base-200/50">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-black text-lg">
                        ⌨️
                    </div>
                    <div>
                        <h3 class="font-black text-base text-base-content">{{ __('کلیدهای میانبر استاندارد حسابداری نپتون') }}</h3>
                        <p class="text-xs text-base-content/60">{{ __('الگوی ارگونومیک سازگار با نرم‌افزارهای هلو، سپیدار و سیستم‌های مدرن ERP') }}</p>
                    </div>
                </div>
                <button type="button" @click="showCheatSheet = false" class="btn btn-ghost btn-sm btn-circle">
                    ✕
                </button>
            </div>

            <!-- Modal Body (Categorized Hotkey Matrix) -->
            <div class="p-6 overflow-y-auto space-y-6 text-sm">

                <!-- 1. Invoice & Document Operations -->
                <div>
                    <h4 class="font-bold text-xs uppercase tracking-wider text-primary mb-3 flex items-center gap-2">
                        <span>🧾</span>
                        <span>{{ __('عملیات صدور فاکتور و ثبت اسناد') }}</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('ثبت سریع فرم / فاکتور جاری') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('کلیک خودکار دکمه ذخیره فعال صفحه') }}</div>
                            </div>
                            <kbd class="kbd kbd-sm font-mono font-bold bg-primary text-primary-content">F2</kbd>
                        </div>

                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('ثبت و تایید نهایی سند / فاکتور') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('تایید قطعی و ایجاد سند دوبل حسابداری') }}</div>
                            </div>
                            <div class="flex gap-1">
                                <kbd class="kbd kbd-sm font-mono bg-base-300">Ctrl</kbd>
                                <span>+</span>
                                <kbd class="kbd kbd-sm font-mono font-bold bg-success text-success-content">Enter</kbd>
                            </div>
                        </div>

                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('ایجاد فاکتور فروش جدید') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('مسیریابی آنی به صفحه صدور فاکتور') }}</div>
                            </div>
                            <kbd class="kbd kbd-sm font-mono font-bold bg-base-300">F4</kbd>
                        </div>

                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('چاپ سریع / فیش حرارتی') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('ارسال فوری سند به پرینتر یا چاپگر POS') }}</div>
                            </div>
                            <kbd class="kbd kbd-sm font-mono font-bold bg-info text-info-content">F8</kbd>
                        </div>
                    </div>
                </div>

                <!-- 2. Search & Navigation -->
                <div>
                    <h4 class="font-bold text-xs uppercase tracking-wider text-primary mb-3 flex items-center gap-2">
                        <span>🔍</span>
                        <span>{{ __('جستجو و ناوبری سریع') }}</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('جستجوی سریع / بارکد کالا') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('فوکوس فوری روی کادر جستجوی صفحه') }}</div>
                            </div>
                            <kbd class="kbd kbd-sm font-mono font-bold bg-base-300">F3</kbd>
                        </div>

                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('پالت فرماندهی سیستم (Command Palette)') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('دسترسی به تمامی ماژول‌ها، منوها و دفاتر') }}</div>
                            </div>
                            <div class="flex gap-1">
                                <kbd class="kbd kbd-sm font-mono bg-base-300">Ctrl</kbd>
                                <span>+</span>
                                <kbd class="kbd kbd-sm font-mono bg-base-300">K</kbd>
                            </div>
                        </div>

                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('بستن پنجره / انصراف') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('بستن مدال‌ها، دراور و پنجره‌های فرعی') }}</div>
                            </div>
                            <kbd class="kbd kbd-sm font-mono font-bold bg-base-300">ESC</kbd>
                        </div>

                        <div class="p-3 bg-base-200/60 rounded-xl flex items-center justify-between border border-base-200">
                            <div>
                                <div class="font-bold text-xs">{{ __('نمایش راهنمای کلیدها') }}</div>
                                <div class="text-[11px] text-base-content/60">{{ __('باز کردن همین پنجره راهنما در هر بخش') }}</div>
                            </div>
                            <kbd class="kbd kbd-sm font-mono font-bold bg-warning text-warning-content">F1</kbd>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 border-t border-base-200 bg-base-200/40 flex items-center justify-between text-xs text-base-content/60">
                <span>{{ __('نکته: در تمام فرم‌ها و فاکتورها، فشردن F2 سریع‌ترین راه ذخیره است.') }}</span>
                <button type="button" @click="showCheatSheet = false" class="btn btn-primary btn-sm rounded-xl">
                    {{ __('متوجه شدم') }}
                </button>
            </div>

        </div>
    </div>

    <!-- Floating Action Toast for Hotkey Trigger Confirmation -->
    <div x-show="toastMessage"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         class="fixed bottom-6 left-6 z-50 bg-primary text-primary-content px-4 py-2.5 rounded-2xl shadow-xl flex items-center gap-2.5 text-xs font-bold pointer-events-none"
         style="display: none;">
        <span class="text-base">⚡</span>
        <span x-text="toastMessage"></span>
    </div>

</div>

<script>
function accountingHotkeys() {
    return {
        showCheatSheet: false,
        toastMessage: '',
        toastTimeout: null,

        init() {
            // Hotkeys initialized
        },

        notify(message) {
            this.toastMessage = message;
            clearTimeout(this.toastTimeout);
            this.toastTimeout = setTimeout(() => {
                this.toastMessage = '';
            }, 1800);
        },

        handleGlobalKeydown(e) {
            // Ignore hotkeys inside standard text inputs if it's normal typing,
            // but functional keys (F1-F12, ESC, Ctrl combinations) are always caught.
            const target = e.target;
            const isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);

            // F1 or Shift + ? -> Open Hotkey Cheat Sheet
            if (e.key === 'F1' || (e.shiftKey && e.key === '?')) {
                e.preventDefault();
                this.showCheatSheet = !this.showCheatSheet;
                return;
            }

            // ESC -> Close cheat sheet or modals
            if (e.key === 'Escape') {
                if (this.showCheatSheet) {
                    e.preventDefault();
                    this.showCheatSheet = false;
                    return;
                }
            }

            // F2 -> Fast Save / Submit Form
            if (e.key === 'F2') {
                e.preventDefault();
                this.triggerFormSubmit(false);
                return;
            }

            // Ctrl + Enter -> Final Approve / Submit with Approval
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                this.triggerFormSubmit(true);
                return;
            }

            // F3 -> Focus Search or Open Command Palette
            if (e.key === 'F3') {
                e.preventDefault();
                this.triggerSearchFocus();
                return;
            }

            // F4 -> New Invoice
            if (e.key === 'F4') {
                e.preventDefault();
                this.notify('انتقال به فاکتور جدید (F4)...');
                window.location.href = '/invoices/create';
                return;
            }

            // F8 -> Direct Print
            if (e.key === 'F8') {
                e.preventDefault();
                this.triggerPrint();
                return;
            }

            // F5 on Invoice form -> prevent accidental reload and focus payment
            if (e.key === 'F5' && window.location.pathname.includes('/invoices/')) {
                e.preventDefault();
                const payBtn = document.querySelector('button[data-action="quick-pay"], button#btn-quick-pay, a[href*="payments"]');
                if (payBtn) {
                    this.notify('تسویه سریع (F5)...');
                    payBtn.click();
                } else {
                    this.notify('صفحه فاکتور: برای تسویه یا ثبت از F2 استفاده کنید');
                }
                return;
            }
        },

        triggerFormSubmit(isApproval = false) {
            // Find active or primary form
            const activeForm = document.activeElement ? document.activeElement.closest('form') : null;
            const form = activeForm || document.querySelector('form.main-form') || document.querySelector('form:not([action*="logout"])');

            if (!form) {
                this.notify('فرم فعالی برای ثبت یافت نشد');
                return;
            }

            if (isApproval) {
                const approveBtn = form.querySelector('button[name="approve"], button.btn-approve, input[name="approve"]');
                if (approveBtn) {
                    this.notify('ثبت و تایید نهایی سند (Ctrl+Enter)...');
                    approveBtn.click();
                    return;
                }
            }

            // Standard submit button
            const submitBtn = form.querySelector('button[type="submit"]:not(.btn-cancel):not(.btn-danger), button.btn-save');
            if (submitBtn) {
                this.notify('ذخیره سریع فرم (F2)...');
                submitBtn.click();
            } else {
                this.notify('در حال ثبت فرم...');
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        },

        triggerSearchFocus() {
            const searchInput = document.querySelector('input[type="search"], input[name="search"], input[placeholder*="جستجو"], input#barcode-input');
            if (searchInput) {
                this.notify('فوکوس روی جستجو (F3)');
                searchInput.focus();
                if (searchInput.select) searchInput.select();
            } else {
                // Trigger Command Palette if search input not on page
                window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }));
            }
        },

        triggerPrint() {
            const printBtn = document.querySelector('button[data-action="print"], button.btn-print, a.btn-print, a[href*="/print"]');
            if (printBtn) {
                this.notify('ارسال به چاپگر (F8)...');
                printBtn.click();
            } else {
                this.notify('چاپ سند (F8)...');
                window.print();
            }
        }
    };
}
</script>
