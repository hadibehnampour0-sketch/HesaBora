<x-app-layout :title="__('Create Customer')">
    @php
        // Phase U: same guided flow as the invoice wizards -- a pure presentation layer over the SAME
        // form fields, route and validation (the edit page never sets wizardMode).
        $isWizard = auth()->user()->isSimpleMode();
    @endphp
    <div class="card-title">{{ __('Create Customer') }}</div>
    <div @if ($isWizard) x-data="{ step: 1, wizardMode: true }" @endif>
        <form action="{{ route('customers.store') }}" method="POST" class="relative">
            <div class="card bg-base-100 shadow-xl rounded-xl">

                @csrf
                <div class="card-body p-4  ">

                    <x-show-message-bags />

                    @if ($isWizard)
                        <ul class="steps w-full mb-4">
                            <li class="step" :class="{ 'step-primary': step >= 1 }">{{ __('Identity Information') }}</li>
                            <li class="step" :class="{ 'step-primary': step >= 2 }">{{ __('Contact Information') }}</li>
                            <li class="step" :class="{ 'step-primary': step >= 3 }">{{ __('Financial & other information') }}</li>
                        </ul>
                    @endif

                    @include('customers.form', ['wizardMode' => $isWizard])

                    @if ($isWizard)
                        <div class="flex justify-between">
                            <button type="button" class="btn rounded-md" x-show="step > 1" @click="step--">{{ __('Back') }}</button>
                            <span x-show="step === 1"></span>
                            <button type="button" class="btn btn-primary rounded-md" x-show="step < 3" @click="step++">{{ __('Next') }}</button>
                            <button type="submit" class="btn btn-primary rounded-md" x-show="step === 3">{{ __('Create') }}</button>
                        </div>
                    @else
                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Create') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

        </form>
    </div>
</x-app-layout>
