{{-- Phase U "Today" dashboard. Data comes from GET home.today (permission-gated per block). Amounts are hidden until revealed, matching the private-metric behaviour of the rest of Home. --}}
<section x-data="todayDashboard('{{ route('home.today') }}')" x-init="load()" class="space-y-4" aria-labelledby="today-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="today-title" class="text-lg font-bold text-base-content">{{ __('Today') }}</h2>
            <p class="text-xs text-base-content/60">{{ __('A quick look at what needs your attention right now.') }}</p>
        </div>
        <button type="button" class="btn btn-sm btn-outline" @click="revealed = ! revealed" :aria-pressed="revealed.toString()">
            <span x-show="! revealed">{{ __('Show amounts') }}</span>
            <span x-show="revealed" style="display: none">{{ __('Hide amounts') }}</span>
        </button>
    </div>

    <template x-if="loading">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="i in 4" :key="i"><div class="skeleton h-28 w-full rounded-box"></div></template>
        </div>
    </template>

    <template x-if="error">
        <div class="alert alert-warning text-sm">
            <span>{{ __('The dashboard could not be loaded.') }}</span>
            <button type="button" class="btn btn-xs" @click="load()">{{ __('Try again') }}</button>
        </div>
    </template>

    <template x-if="data && ! Object.keys(data.blocks).length">
        <div class="alert text-sm">{{ __('Nothing to show yet for your permissions.') }}</div>
    </template>

    <div x-show="data" style="display: none" class="space-y-4">
        {{-- KPI cards --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" x-show="block('sales')" class="today-kpi group">
                <span class="today-kpi-label">{{ __('Sales today') }}</span>
                <span class="today-kpi-value" x-text="money(block('sales')?.today_sell.amount)"></span>
                <span class="today-kpi-sub"><b x-text="num(block('sales')?.today_sell.count)"></b> {{ __('invoices') }}</span>
            </a>
            <a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}" x-show="block('sales')" class="today-kpi group">
                <span class="today-kpi-label">{{ __('Purchases today') }}</span>
                <span class="today-kpi-value" x-text="money(block('sales')?.today_buy.amount)"></span>
                <span class="today-kpi-sub"><b x-text="num(block('sales')?.today_buy.count)"></b> {{ __('invoices') }}</span>
            </a>
            <a href="{{ route('ageing.receivables') }}" x-show="block('receivables')" class="today-kpi group">
                <span class="today-kpi-label">{{ __('Receivables') }}</span>
                <span class="today-kpi-value" x-text="money(block('receivables')?.total)"></span>
                <span class="today-kpi-sub text-error">{{ __('Overdue') }}: <b x-text="money(block('receivables')?.overdue)"></b></span>
            </a>
            <a href="{{ route('ageing.payables') }}" x-show="block('payables')" class="today-kpi group">
                <span class="today-kpi-label">{{ __('Payables') }}</span>
                <span class="today-kpi-value" x-text="money(block('payables')?.total)"></span>
                <span class="today-kpi-sub text-warning">{{ __('Overdue') }}: <b x-text="money(block('payables')?.overdue)"></b></span>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- 7-day sell vs buy --}}
            <div x-show="block('sales')" class="today-panel lg:col-span-2">
                <div class="today-panel-title">{{ __('Sales and purchases, last 7 days') }}</div>
                <div class="relative h-64 transition" :class="revealed ? '' : 'blur-md pointer-events-none select-none'">
                    <canvas x-ref="salesChart"></canvas>
                </div>
            </div>

            {{-- Receivables split --}}
            <div x-show="block('receivables')" class="today-panel">
                <div class="today-panel-title">{{ __('Receivables') }}</div>
                <div class="relative mx-auto h-44 w-44 transition" :class="revealed ? '' : 'blur-md pointer-events-none select-none'">
                    <canvas x-ref="receivablesChart"></canvas>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-center text-xs">
                    <div><div class="text-base-content/60">{{ __('Current') }}</div><div class="font-bold" x-text="money(block('receivables')?.current)"></div></div>
                    <div><div class="text-base-content/60">{{ __('Overdue') }}</div><div class="font-bold text-error" x-text="money(block('receivables')?.overdue)"></div></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Cheques --}}
            <div x-show="block('cheques')" class="today-panel">
                <div class="flex items-center justify-between">
                    <div class="today-panel-title">{{ __('Cheques') }}</div>
                    <a href="{{ route('cheques.index') }}" class="link text-xs">{{ __('View all') }}</a>
                </div>
                <ul class="mt-3 space-y-2 text-sm">
                    <li class="today-row"><span>{{ __('Receivable cheques due in 7 days') }}</span><span><b x-text="num(block('cheques')?.receivable.due_soon.count)"></b> · <span x-text="money(block('cheques')?.receivable.due_soon.amount)"></span></span></li>
                    <li class="today-row"><span class="text-error">{{ __('Receivable cheques overdue') }}</span><span><b x-text="num(block('cheques')?.receivable.overdue.count)"></b> · <span x-text="money(block('cheques')?.receivable.overdue.amount)"></span></span></li>
                    <li class="today-row"><span class="text-error">{{ __('Bounced cheques') }}</span><span><b x-text="num(block('cheques')?.receivable.bounced.count)"></b> · <span x-text="money(block('cheques')?.receivable.bounced.amount)"></span></span></li>
                    <li class="today-row"><span>{{ __('Payable cheques due in 7 days') }}</span><span><b x-text="num(block('cheques')?.payable.due_soon.count)"></b> · <span x-text="money(block('cheques')?.payable.due_soon.amount)"></span></span></li>
                    <li class="today-row"><span class="text-warning">{{ __('Payable cheques overdue') }}</span><span><b x-text="num(block('cheques')?.payable.overdue.count)"></b> · <span x-text="money(block('cheques')?.payable.overdue.amount)"></span></span></li>
                </ul>
            </div>

            {{-- Top overdue debtors --}}
            <div x-show="block('receivables')" class="today-panel">
                <div class="flex items-center justify-between">
                    <div class="today-panel-title">{{ __('Customers with overdue balances') }}</div>
                    <a href="{{ route('ageing.receivables') }}" class="link text-xs">{{ __('View all') }}</a>
                </div>
                <template x-if="! (block('receivables')?.top_overdue || []).length">
                    <div class="mt-3 text-sm text-success">{{ __('No overdue receivables. 🎉') }}</div>
                </template>
                <ul class="mt-3 space-y-2 text-sm">
                    <template x-for="row in (block('receivables')?.top_overdue || [])" :key="row.customer_id">
                        <li class="today-row">
                            <a :href="'{{ url('ageing/statement') }}/' + row.customer_id + '/receivables'" class="link-hover font-medium" x-text="row.name"></a>
                            <span><span class="text-base-content/60" x-text="num(row.oldest_days) + ' {{ __('days') }}'"></span> · <b x-text="money(row.remaining)"></b></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
</section>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('todayDashboard', (url) => ({
                data: null,
                loading: true,
                error: false,
                revealed: false,
                charts: {},
                lang: document.documentElement.lang || 'fa',
                unit: @js(__('Rial')),

                block(name) { return this.data?.blocks?.[name] ?? null; },
                num(v) { return new Intl.NumberFormat(this.lang).format(v ?? 0); },
                money(v) {
                    if (! this.revealed) { return '••••••'; }
                    return new Intl.NumberFormat(this.lang, { maximumFractionDigits: 0 }).format(v ?? 0) + ' ' + this.unit;
                },
                color(name, fallback) {
                    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
                    return v || fallback;
                },

                async load() {
                    this.loading = true; this.error = false;
                    try {
                        const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                        if (! res.ok) { throw new Error('HTTP ' + res.status); }
                        this.data = await res.json();
                        this.loading = false;
                        this.$nextTick(() => this.draw());
                    } catch (e) {
                        this.error = true; this.loading = false;
                    }
                },

                draw() {
                    Object.values(this.charts).forEach((c) => c.destroy());
                    this.charts = {};
                    const primary = this.color('--color-primary', '#0b5a8e');
                    const accent = this.color('--color-accent', '#e59a2f');
                    const error = this.color('--color-error', '#d9483b');
                    const muted = this.color('--color-base-300', '#dfe7f1');

                    const sales = this.block('sales');
                    if (sales && this.$refs.salesChart) {
                        const fa = this.lang.startsWith('fa');
                        this.charts.sales = new Chart(this.$refs.salesChart, {
                            type: 'bar',
                            data: {
                                labels: sales.series.map((d) => new Date(d.date).toLocaleDateString(fa ? 'fa-IR' : undefined, { month: 'short', day: 'numeric' })),
                                datasets: [
                                    { label: @js(__('Sales')), data: sales.series.map((d) => d.sell), backgroundColor: primary, borderRadius: 6 },
                                    { label: @js(__('Purchases')), data: sales.series.map((d) => d.buy), backgroundColor: accent, borderRadius: 6 },
                                ],
                            },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } },
                        });
                    }

                    const rec = this.block('receivables');
                    if (rec && this.$refs.receivablesChart) {
                        const hasData = rec.total > 0;
                        this.charts.rec = new Chart(this.$refs.receivablesChart, {
                            type: 'doughnut',
                            data: {
                                labels: [@js(__('Current')), @js(__('Overdue'))],
                                datasets: [{ data: hasData ? [rec.current, rec.overdue] : [1], backgroundColor: hasData ? [primary, error] : [muted], borderWidth: 0 }],
                            },
                            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: { enabled: hasData } } },
                        });
                    }
                },

                init() {
                    window.addEventListener('theme:changed', () => this.$nextTick(() => this.draw()));
                },
            }));
        });
    </script>
@endpush
