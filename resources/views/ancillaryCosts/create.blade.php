<x-app-layout :title="__('Create Ancillary Cost')">
    @php
        // Phase U: guided two-step flow in Simple mode -- a pure presentation layer over the SAME form
        // fields, route and validation (the edit page never sets wizardMode).
        $isWizard = auth()->user()->isSimpleMode();
    @endphp
    <div @if ($isWizard) x-data="{ step: 1, wizardMode: true }" @endif>
        <form action="{{ isset($invoice) && $invoice ? route('invoices.ancillary-costs.store', $invoice) : route('ancillary-costs.store') }}"
            method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Ancillary Cost') }}</h2>
                <x-show-message-bags />

                @if ($isWizard)
                    <ul class="steps w-full mb-4">
                        <li class="step" :class="{ 'step-primary': step >= 1 }">{{ __('Supplier & cost info') }}</li>
                        <li class="step" :class="{ 'step-primary': step >= 2 }">{{ __('Items & submit') }}</li>
                    </ul>
                @endif

                @include('ancillaryCosts.form', ['wizardMode' => $isWizard])
            </div>
        </form>
    </div>
</x-app-layout>
