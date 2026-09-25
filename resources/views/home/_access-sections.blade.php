@if (! $canFinancial && $quickLinks->isNotEmpty())
    <nav aria-label="{{ __('Quick Access') }}">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ($quickLinks as $link)
                @include('home._quick-access-link', compact('link'))
            @endforeach
        </div>
    </nav>
@elseif ($hasBusinessPerms && $canFinancial)
    <section aria-labelledby="work-areas-title">
        <div class="grid grid-cols-1 gap-4 {{ $businessGridClass }}">
            @if ($canFinancial)
                <article data-home-area="accounting" @if (in_array($homeVariant, ['accounting', 'admin'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/25 hover:shadow-lg {{ $businessCardClass }}">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-primary"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-primary/10 p-2.5 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 2h9l4 4v16H6V2Zm9 0v5h4M9 12h7M9 16h7" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-bold text-base-content">{{ __('Accounting') }}</h3>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Documents, cheques and bank accounts') }}</p>
                        </div>
                    </div>

                    @include('home._latest-quick-access-data', ['area' => 'accounting', 'items' => $quickAccessRecentData['accounting']])

                    <div class="mt-4 flex gap-1">
                        @can('documents.index')
                            <a href="{{ route('documents.index') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-primary/15 hover:bg-primary/5">
                                <span class="truncate">{{ __('Documents') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('cheques.index')
                            <a href="{{ route('cheques.index') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-primary/15 hover:bg-primary/5">
                                <span class="truncate">{{ __('Cheque Management') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('bank-accounts.index')
                            <a href="{{ route('bank-accounts.index') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-primary/15 hover:bg-primary/5">
                                <span class="truncate">{{ __('Bank Accounts') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>
                </article>
            @endif

            @if ($canSales)
                <article data-home-area="sales" @if (in_array($homeVariant, ['sales', 'operations'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-success/25 hover:shadow-lg {{ $businessCardClass }}">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-success"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-success/10 p-2.5 text-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5h2l2 11h10l2-8H6m3 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-bold text-base-content">{{ __('Invoices') }}</h3>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Invoices, add customer') }}</p>
                        </div>
                    </div>

                    @include('home._latest-quick-access-data', ['area' => 'sales', 'items' => $quickAccessRecentData['sales']])

                    <div class="mt-4 flex gap-1">
                        <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-success/15 hover:bg-success/5">
                            <span class="truncate">{{ __('Sell Invoices') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        <a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-success/15 hover:bg-success/5">
                            <span class="truncate">{{ __('Buy Invoices') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        @can('customers.create')
                            <a href="{{ route('customers.create') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-success/15 hover:bg-success/5">
                                <span class="truncate">{{ __('Add Customer') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>
                </article>
            @endif
        </div>
    </section>
@endif
