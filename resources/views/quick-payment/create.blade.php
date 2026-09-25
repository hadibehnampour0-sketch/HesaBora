@php
    $isReceive = $direction === 'receive';
    $title = $isReceive ? __('Collect money') : __('Pay suppliers');
@endphp
<x-app-layout :title="$title">
    <x-show-message-bags />

    <div class="mx-auto max-w-3xl space-y-4" x-data="quickPayment(@js($parties), @js($accounts), '{{ route('invoices.payments.store', ['invoice' => '__ID__']) }}', '{{ route('quick-payment.store', $direction) }}')">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold">{{ $title }}</h1>
                <p class="text-sm text-base-content/60">
                    {{ $isReceive ? __('Record money a customer has paid you against their open invoices.') : __('Record money you paid a supplier against their open invoices.') }}
                </p>
            </div>
            <div class="join">
                <a href="{{ route('quick-payment.create', 'receive') }}" class="btn btn-sm join-item {{ $isReceive ? 'btn-primary' : 'btn-outline' }}">{{ __('Collect money') }}</a>
                <a href="{{ route('quick-payment.create', 'pay') }}" class="btn btn-sm join-item {{ ! $isReceive ? 'btn-primary' : 'btn-outline' }}">{{ __('Pay suppliers') }}</a>
            </div>
        </div>

        @if (count($parties) === 0)
            <div class="alert alert-success">{{ $isReceive ? __('No customer owes you anything right now.') : __('You do not owe any supplier right now.') }}</div>
        @else
            {{-- 1. who --}}
            <div class="today-panel">
                <div class="today-panel-title">1. {{ $isReceive ? __('Who paid you?') : __('Whom did you pay?') }}</div>
                <input type="search" x-model="search" class="input input-bordered mt-3 w-full" placeholder="{{ __('Search') }}">
                <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                    <template x-for="party in filtered" :key="party.id">
                        <li>
                            <button type="button" class="today-row w-full text-start" :class="partyId === party.id ? 'ring-2 ring-primary' : ''" @click="choose(party)">
                                <span class="font-medium" x-text="party.name"></span>
                                <span class="text-xs text-base-content/60" x-text="fmt(party.total)"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- 2. which invoice + details --}}
            <form method="POST" :action="action" class="space-y-4" x-show="party" style="display: none">
                @csrf
                <input type="hidden" name="party_id" :value="partyId" :disabled="invoiceId">
                <div class="today-panel">
                    <div class="today-panel-title">2. {{ __('Which invoice?') }}</div>
                    <ul class="mt-3 space-y-1">
                        <li>
                            <label class="today-row cursor-pointer" :class="!invoiceId ? 'ring-2 ring-primary' : ''">
                                <span class="flex items-center gap-2">
                                    <input type="radio" class="radio radio-sm radio-primary" name="_invoice" value="" :checked="!invoiceId" @change="pick(null)">
                                    <span>{{ __('Settle the oldest invoices first (automatic)') }}</span>
                                </span>
                                <b x-text="fmt(party?.total)"></b>
                            </label>
                        </li>
                        <template x-for="inv in (party?.invoices || [])" :key="inv.id">
                            <li>
                                <label class="today-row cursor-pointer" :class="invoiceId === inv.id ? 'ring-2 ring-primary' : ''">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" class="radio radio-sm radio-primary" name="_invoice" :value="inv.id" @change="pick(inv)">
                                        <span>{{ __('Invoice') }} <b x-text="inv.number"></b> · <span x-text="inv.date"></span></span>
                                    </span>
                                    <span class="text-xs"><span class="text-base-content/60" x-text="inv.age_days + ' {{ __('days') }}'"></span> · <b x-text="fmt(inv.remaining)"></b></span>
                                </label>
                            </li>
                        </template>
                    </ul>
                </div>

                <div class="today-panel">
                    <div class="today-panel-title">3. {{ __('Details') }}</div>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="form-control">
                            <span class="label-text mb-1 text-xs">{{ __('Amount') }}</span>
                            <input type="number" step="any" min="0" name="amount" x-model="amount" class="input input-bordered" required>
                            <span class="mt-1 text-xs text-base-content/60">{{ __('Remaining Amount') }}: <b x-text="fmt(invoice ? invoice.remaining : party?.total)"></b></span>
                        </label>
                        <label class="form-control">
                            <span class="label-text mb-1 text-xs">{{ __('Settlement Account') }}</span>
                            <select name="subject_id" class="select select-bordered" required>
                                <option value="">{{ __('Select an account') }}</option>
                                <template x-for="group in accounts" :key="group.label">
                                    <optgroup :label="group.label">
                                        <template x-for="opt in group.options" :key="opt.id">
                                            <option :value="opt.id" x-text="opt.name + ' (' + opt.code + ')'"></option>
                                        </template>
                                    </optgroup>
                                </template>
                            </select>
                        </label>
                        <label class="form-control">
                            <span class="label-text mb-1 text-xs">{{ __('Date') }}</span>
                            <input type="text" name="date" data-jdp autocomplete="off" readonly class="input input-bordered" value="{{ convertToJalali(now(), true) }}">
                        </label>
                        <label class="form-control">
                            <span class="label-text mb-1 text-xs">{{ __('Reference Number') }}</span>
                            <input type="text" name="reference_number" maxlength="20" class="input input-bordered">
                        </label>
                        <label class="form-control sm:col-span-2">
                            <span class="label-text mb-1 text-xs">{{ __('Description') }}</span>
                            <textarea name="description" rows="2" class="textarea textarea-bordered"></textarea>
                        </label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </div>
            </form>
        @endif
    </div>

    @push('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('quickPayment', (parties, accounts, template, storeUrl) => ({
                    parties, accounts, search: '', partyId: null, party: null, invoiceId: null, invoice: null, amount: '',
                    get filtered() {
                        const q = this.search.trim().toLowerCase();
                        return q ? this.parties.filter((p) => p.name.toLowerCase().includes(q)) : this.parties;
                    },
                    get action() { return this.invoiceId ? template.replace('__ID__', this.invoiceId) : storeUrl; },
                    fmt(v) { return new Intl.NumberFormat(document.documentElement.lang || 'fa', { maximumFractionDigits: 0 }).format(v ?? 0); },
                    choose(p) { this.partyId = p.id; this.party = p; this.invoiceId = null; this.invoice = null; this.amount = p.total; },
                    pick(inv) { this.invoiceId = inv ? inv.id : null; this.invoice = inv; this.amount = inv ? inv.remaining : this.party.total; },
                }));
            });
        </script>
    @endpush
</x-app-layout>
