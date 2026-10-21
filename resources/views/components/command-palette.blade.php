<div x-data="commandPalette()"
     x-init="init()"
     @keydown.window.prevent.cmd.k="openPalette()"
     @keydown.window.prevent.ctrl.k="openPalette()"
     class="relative z-50">

    <!-- Modal Backdrop -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
         @click="closePalette()"
         style="display: none;"></div>

    <!-- Palette Container -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 flex items-start justify-center pt-16 md:pt-24 p-4 pointer-events-none"
         style="display: none;">

        <div class="pointer-events-auto w-full max-w-2xl bg-base-100 border border-base-300 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[75vh]"
             @click.away="closePalette()">

            <!-- Search Input Bar -->
            <div class="p-3.5 border-b border-base-200 flex items-center gap-3 bg-base-200/40">
                <svg class="w-5 h-5 text-primary shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text"
                       x-ref="searchInput"
                       x-model="searchQuery"
                       @keydown.arrow-down.prevent="navigateNext()"
                       @keydown.arrow-up.prevent="navigatePrev()"
                       @keydown.enter.prevent="selectActive()"
                       @keydown.escape.prevent="closePalette()"
                       placeholder="{{ __('جستجوی سریع بخش‌ها، فاکتورها، مشتریان و عملیات...') }}"
                       class="w-full bg-transparent text-sm focus:outline-none text-base-content placeholder:text-base-content/40" />

                <div class="flex items-center gap-1.5 shrink-0 text-xs text-base-content/40">
                    <kbd class="px-1.5 py-0.5 rounded bg-base-300 text-[10px] font-mono">ESC</kbd>
                    <span>{{ __('خروج') }}</span>
                </div>
            </div>

            <!-- Filtered Results List -->
            <div class="overflow-y-auto p-2 space-y-1 text-sm flex-1" x-ref="resultsList">
                <template x-for="(item, index) in filteredItems" :key="item.url">
                    <a :href="item.url"
                       class="flex items-center justify-between p-2.5 rounded-xl transition cursor-pointer text-xs md:text-sm"
                       :class="index === selectedIndex ? 'bg-primary text-primary-content font-medium' : 'hover:bg-base-200 text-base-content'"
                       @mouseenter="selectedIndex = index">
                        <div class="flex items-center gap-3">
                            <span class="text-base" x-text="item.icon"></span>
                            <div>
                                <div class="font-bold" x-text="item.title"></div>
                                <div class="text-[11px] opacity-70" x-text="item.category"></div>
                            </div>
                        </div>
                        <span class="text-[10px] opacity-50 font-mono">↵</span>
                    </a>
                </template>

                <template x-if="filteredItems.length === 0">
                    <div class="text-center py-8 text-base-content/50 text-xs">
                        {{ __('هیچ نتیجه‌ای متناسب با عبارت جستجو یافت نشد.') }}
                    </div>
                </template>
            </div>

            <!-- Footer Hint -->
            <div class="px-4 py-2 border-t border-base-200 text-[11px] text-base-content/50 flex items-center justify-between bg-base-200/20">
                <div class="flex items-center gap-2">
                    <span>کلیدهای <kbd class="px-1 py-0.2 rounded bg-base-300 font-mono">↑</kbd> <kbd class="px-1 py-0.2 rounded bg-base-300 font-mono">↓</kbd> جهت حرکت</span>
                    <span>•</span>
                    <span>کلید <kbd class="px-1 py-0.2 rounded bg-base-300 font-mono">Enter</kbd> جهت انتخاب</span>
                </div>
                <div class="font-bold text-primary flex items-center gap-1">
                    <span>دخل</span>
                    <span class="badge badge-primary badge-xs">Copilot</span>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function commandPalette() {
        return {
            isOpen: false,
            searchQuery: '',
            selectedIndex: 0,
            items: [
                // Invoices & Sales
                { title: 'صدور فاکتور فروش جدید', category: 'فروش و درآمد', icon: '📝', url: '{{ route('invoices.create', ['invoice_type' => 'sell']) }}' },
                { title: 'صندوق فروشگاهی سریع (POS)', category: 'فروش خرده‌فروشی', icon: '🛒', url: '{{ route('pos.terminal') }}' },
                { title: 'لیست فاکتورهای فروش', category: 'فروش و درآمد', icon: '📋', url: '{{ route('invoices.index', ['invoice_type' => 'sell']) }}' },
                { title: 'صدور فاکتور خرید', category: 'خرید و تأمین', icon: '🛍️', url: '{{ route('invoices.create', ['invoice_type' => 'buy']) }}' },
                { title: 'فروش اقساطی و محاسبات سود/دیرکرد', category: 'تسهیلات و مالی', icon: '💳', url: '{{ route('financing.index') }}' },

                // AI & Smart Tools
                { title: 'دستیار مالی هوش مصنوعی (Copilot)', category: 'هوش مصنوعی', icon: '✨', url: '{{ route('copilot.index') }}' },
                { title: 'ورود فاکتور با هوش مصنوعی و OCR', category: 'هوش مصنوعی اسنادی', icon: '📄', url: '{{ route('ai-invoices.index') }}' },
                { title: 'باشگاه مشتریان و کیف پول اعتباری', category: 'وفادارسازی', icon: '🎁', url: '{{ route('loyalty.index') }}' },
                { title: 'اتوماسیون پیامک هوشمند رویدادی', category: 'ارتباطات', icon: '💬', url: '{{ route('sms.index') }}' },
                { title: 'سامانه استعلام شاهکار و شبا', category: 'اعتبارسنجی', icon: '🔍', url: '{{ route('inquiries.index') }}' },

                // Customers
                { title: 'تعریف مشتری جدید', category: 'مشتریان', icon: '👤', url: '{{ route('customers.create') }}' },
                { title: 'لیست مشتریان و طرف‌های حساب', category: 'مشتریان', icon: '👥', url: '{{ route('customers.index') }}' },
                { title: 'گزارش سنی مطالبات (بدهکاران معوق)', category: 'گزارشات وصول', icon: '⏳', url: '{{ route('ageing.receivables') }}' },

                // Warehousing & Products
                { title: 'تعریف محصول / کالای جدید', category: 'انبار و محصولات', icon: '📦', url: '{{ route('products.create') }}' },
                { title: 'داشبورد انبار و موجودی کالاها', category: 'انبار و محصولات', icon: '🏭', url: '{{ route('warehouse.dashboard') }}' },
                { title: 'فرمول ساخت و سفارشات تولید (BOM)', category: 'تولید سبک', icon: '⚙️', url: '{{ route('production.recipes.index') }}' },

                // Accounting & Treasury
                { title: 'ثبت سند حسابداری جدید', category: 'دفاتر حسابداری', icon: '📑', url: '{{ route('documents.create') }}' },
                { title: 'مدیریت چک‌های صیادی و واگذاری', category: 'خزانه‌داری', icon: '🏦', url: '{{ route('cheques.index') }}' },
                { title: 'مغایرت‌گیری بانکی خودکار', category: 'خزانه‌داری', icon: '🔄', url: '{{ route('bank-accounts.index') }}' },
                { title: 'ترازنامه و صورت‌های مالی', category: 'گزارشات حسابداری', icon: '📊', url: '{{ route('reports.result') }}' },
                { title: 'داشبورد امروز من', category: 'مدیریت روزانه', icon: '🧭', url: '{{ route('home.today') }}' },

                // Tax & Moadian
                { title: 'میز کار سامانه مودیان', category: 'مالیات و انطباق', icon: '⚖️', url: '{{ route('tax-readiness.index') }}' },
                { title: 'گزارش فصلی ارزش افزوده (ماده ۱۰)', category: 'مالیات و انطباق', icon: '📑', url: '{{ route('seasonal-tax.index') }}' },

                // Migration & Settings
                { title: 'مهاجرت اطلاعات از رقبا (هلو/سپیدار)', category: 'انتقال داده', icon: '🔄', url: '{{ route('migration.index') }}' },
                { title: 'مدیریت لایسنس و اشتراک ماژولار', category: 'پیکربندی', icon: '🔐', url: '{{ route('license.index') }}' },
                { title: 'تنظیمات حساب کاربری و پوسته', category: 'پیکربندی', icon: '⚙️', url: '{{ route('account.edit') }}' }
            ],

            init() {
                window.openCommandPalette = () => this.openPalette();
            },

            get filteredItems() {
                if (!this.searchQuery.trim()) {
                    return this.items.slice(0, 8);
                }
                const q = this.searchQuery.toLowerCase();
                return this.items.filter(item =>
                    item.title.toLowerCase().includes(q) ||
                    item.category.toLowerCase().includes(q)
                ).slice(0, 10);
            },

            openPalette() {
                this.isOpen = true;
                this.searchQuery = '';
                this.selectedIndex = 0;
                this.$nextTick(() => {
                    if (this.$refs.searchInput) this.$refs.searchInput.focus();
                });
            },

            closePalette() {
                this.isOpen = false;
            },

            navigateNext() {
                if (this.selectedIndex < this.filteredItems.length - 1) {
                    this.selectedIndex++;
                }
            },

            navigatePrev() {
                if (this.selectedIndex > 0) {
                    this.selectedIndex--;
                }
            },

            selectActive() {
                if (this.filteredItems[this.selectedIndex]) {
                    window.location.href = this.filteredItems[this.selectedIndex].url;
                }
            }
        };
    }
</script>
