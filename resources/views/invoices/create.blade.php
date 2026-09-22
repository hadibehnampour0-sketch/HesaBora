<x-app-layout :title="$isServiceBuy ? __('Create') . ' ' . __('Service Buy Invoice') : __('Create Invoice')">
    @php
        $invoice = $invoice ?? new \App\Models\Invoice();
        // P8.2 (Zero-Knowledge UX): the guided wizard is scoped to a brand
        // new Sell invoice in Simple mode -- editing an existing invoice
        // always uses the full form regardless of mode, and every other
        // invoice type/edit flow is completely untouched.
        $isSellWizard = $invoice_type === 'sell' && ! $invoice->exists && auth()->user()->isSimpleMode();
    @endphp
    <div @if ($isSellWizard) x-data="{ step: 1, wizardMode: true }" @endif>
        <form action="{{ route('invoices.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">
                    @if ($isReturnServiceBuy)
                        {{ __('Add') . ' ' . __('Return Service Buy Invoice') }}
                    @else
                        {{ __('Add') . ' ' . ($isServiceBuy ? __('Service Buy Invoice') : __($invoice_type)) }}
                    @endif
                </h2>
                <x-show-message-bags />

                @if ($isSellWizard)
                    <ul class="steps w-full mb-4">
                        <li class="step" :class="{ 'step-primary': step >= 1 }">{{ __('Buyer & invoice info') }}</li>
                        <li class="step" :class="{ 'step-primary': step >= 2 }">{{ __('Items & discounts') }}</li>
                        <li class="step" :class="{ 'step-primary': step >= 3 }">{{ __('Description & submit') }}</li>
                    </ul>
                @endif

                @switch($invoice_type)
                    @case('sell')
                        @include('invoices.forms.sell', ['wizardMode' => $isSellWizard])
                    @break

                    @case('buy')
                        @if ($isServiceBuy)
                            @include('invoices.forms.buy_service')
                        @else
                            @include('invoices.forms.buy')
                        @endif
                    @break

                    @case('return_sell')
                        @include('invoices.forms.return_sell')
                    @break

                    @case('return_buy')
                        @if ($isReturnServiceBuy)
                            @php($isServiceBuy = true)
                        @endif
                        @include('invoices.forms.return_buy')
                    @break

                    @default
                        <p>{{ __('Invalid invoice type') }}</p>
                @endswitch
            </div>
        </form>
    </div>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce

</x-app-layout>
