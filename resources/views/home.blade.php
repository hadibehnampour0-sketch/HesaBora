<x-app-layout :title="__('Home')">
    @php
        $availableQuickLinks = collect([
            ['permission' => 'invoices.index', 'area' => 'sell-invoices-link', 'label' => __('Sell Invoices'), 'href' => route('invoices.index', ['invoice_type' => 'sell']), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'invoices.index', 'area' => 'buy-invoices-link', 'label' => __('Buy Invoices'), 'href' => route('invoices.index', ['invoice_type' => 'buy']), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'invoices.create', 'area' => 'create-sell-invoice-link', 'label' => __('Create Sell Invoice'), 'href' => route('invoices.create', ['invoice_type' => 'sell']), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'customers.create', 'area' => 'create-customer-link', 'label' => __('Create Customer'), 'href' => route('customers.create'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'crm.dashboard', 'area' => 'crm-dashboard', 'label' => __('CRM Dashboard'), 'href' => route('crm.dashboard'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'ancillary-costs.index', 'area' => 'ancillary-costs', 'label' => __('Ancillary Costs'), 'href' => route('ancillary-costs.index'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'services.create', 'area' => 'create-service-link', 'label' => __('Add Service'), 'href' => route('services.create'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'products.create', 'area' => 'create-product-link', 'label' => __('Create Product'), 'href' => route('products.create'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'warehouse.dashboard', 'area' => 'warehouse-dashboard', 'label' => __('Warehouse Dashboard'), 'href' => route('warehouse.dashboard'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'products.report', 'area' => 'inventory-report', 'label' => __('Warehouse Report'), 'href' => route('products.report'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'products.import', 'area' => 'import-products-link', 'label' => __('Import Products'), 'href' => route('products.import'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'products.export', 'area' => 'export-products-link', 'label' => __('Receive Products Report'), 'href' => route('products.export'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'employee-portal.dashboard', 'area' => 'employee-overview', 'label' => __('My Portal'), 'href' => route('employee-portal.dashboard'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'employee-portal.employee.show', 'area' => 'employee-profile', 'label' => __('Employment Profile'), 'href' => route('employee-portal.employee.show'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'employee-portal.attendance-logs', 'area' => 'employee-attendance', 'label' => __('Attendance'), 'href' => route('employee-portal.attendance-logs'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'employee-portal.monthly-attendances', 'area' => 'employee-monthly-attendance', 'label' => __('Monthly Attendance'), 'href' => route('employee-portal.monthly-attendances'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'employee-portal.payrolls', 'area' => 'employee-payroll', 'label' => __('Payroll'), 'href' => route('employee-portal.payrolls'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
            ['permission' => 'employee-portal.personnel-requests.index', 'area' => 'employee-requests', 'label' => __('Personnel Requests'), 'href' => route('employee-portal.personnel-requests.index'), 'style' => 'border-base-300 bg-base-100 text-base-content hover:border-primary/40 hover:bg-primary/5'],
        ])->filter(fn (array $link) => auth()->user()->can($link['permission']))->values();
    
        $quickLinks = $availableQuickLinks->take(12);
        $standaloneLinks = collect([
            'create-product-link',
            'warehouse-dashboard',
            'create-service-link',
            'ancillary-costs',
            'create-customer-link',
            'crm-dashboard',
        ])->map(fn (string $area) => $availableQuickLinks->firstWhere('area', $area))->filter();
    
        $businessGridClass = $canSales ? 'md:grid-cols-2 xl:grid-cols-4' : 'lg:max-w-2xl';
        $businessCardClass = $canSales ? 'xl:col-span-2' : '';
    @endphp

    <x-show-message-bags />

    <div class="home-dashboard pb-6">
        @include('home._header', compact('homeVariant'))

        @if ($hasBusinessPerms)
            @include('home._today')
        @else
            @include('home._financial-values')
        @endif

        @include('home._access-sections')
    </div>

    @push('scripts')
        <script>
            window.privateHomeMetric = function (url) {
                return {
                    revealed: false,
                    loaded: false,
                    loading: false,
                    error: false,
                    formattedValue: '',
                    unit: '',

                    async toggle() {
                        if (this.revealed) {
                            this.revealed = false;
                            return;
                        }

                        this.revealed = true;
                        if (!this.loaded && !this.loading) {
                            await this.load();
                        }
                    },

                    async load() {
                        this.loading = true;
                        this.error = false;

                        try {
                            const response = await fetch(url, {
                                cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                throw new Error(`Metric request failed with status ${response.status}`);
                            }

                            const data = await response.json();
                            this.formattedValue = data.formattedValue;
                            this.unit = data.unit;
                            this.loaded = true;
                        } catch (error) {
                            this.error = true;
                        } finally {
                            this.loading = false;
                        }
                    },

                    async retry() {
                        await this.load();
                    },

                    reset() {
                        this.revealed = false;
                        this.loaded = false;
                        this.loading = false;
                        this.error = false;
                        this.formattedValue = '';
                        this.unit = '';
                    },
                };
            };

            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    window.dispatchEvent(new CustomEvent('home-private-reset'));
                }
            });
        </script>
    @endpush
</x-app-layout>
