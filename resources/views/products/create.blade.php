<x-app-layout :title="__('Create Product')">
    @php
        // Phase U: guided three-step flow in Simple mode -- a pure presentation layer over the SAME
        // form fields, route and validation (the edit page never sets wizardMode).
        $isWizard = auth()->user()->isSimpleMode();
    @endphp
    <div class="card bg-base-100 shadow-xl" @if ($isWizard) x-data="{ step: 1, wizardMode: true }" @endif>
        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add product') }}</h2>
                <x-show-message-bags />

                @if ($isWizard)
                    <ul class="steps w-full mb-4">
                        <li class="step" :class="{ 'step-primary': step >= 1 }">{{ __('Name & group') }}</li>
                        <li class="step" :class="{ 'step-primary': step >= 2 }">{{ __('Price & stock') }}</li>
                        <li class="step" :class="{ 'step-primary': step >= 3 }">{{ __('More details') }}</li>
                    </ul>
                @endif

                @include('products.form', ['wizardMode' => $isWizard])

                @if ($isWizard)
                    <div class="card-actions justify-between">
                        <button type="button" class="btn rounded-md" x-show="step > 1" @click="step--">{{ __('Back') }}</button>
                        <span x-show="step === 1"></span>
                        <button type="button" class="btn btn-primary rounded-md" x-show="step < 3" @click="step++">{{ __('Next') }}</button>
                        <button type="submit" class="btn btn-primary rounded-md" x-show="step === 3">{{ __('Create') }}</button>
                    </div>
                @else
                    <div class="card-actions justify-end">
                        <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                    </div>
                @endif
            </div>
        </form>
    </div>

</x-app-layout>
