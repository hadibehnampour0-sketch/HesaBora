<x-login-layout>
    @include('auth.partials.header')

    <div x-data="loginForm()" class="login-bg flex-1 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 border border-base-300 bg-base-100 shadow-xl">
                <form method="POST" action="{{ route('register.email') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Register') }}</h1>
                    <x-show-message-bags />
                    
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="name" id="name" title="{{ __('Name') }}" placeHolder="{{ __('Enter your name') }}" :input_value="old('name')" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="email" id="email" title="{{ __('Email') }}" placeHolder="{{ __('Enter your email') }}" :type="'email'" :input_value="old('email')" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="password" id="password" title="{{ __('Password') }}" placeHolder="{{ __('Enter your password') }}" :type="'password'" />
                    <x-text-input class="mt-1 w-full max-w-xs" input_name="password_confirmation" id="password_confirmation" title="{{ __('Confirm Password') }}" placeHolder="{{ __('Confirm your password') }}" :type="'password'" />
                    <button type="submit" class="btn btn-primary w-full mt-4 max-w-xs">{{ __('Register') }}</button>
                </form>

                <div class="mt-3">
                    <p class="text-center">{{ __('Already have an account?') }} <a class="text-info" href="{{ route('login') }}">{{ __('Login') }}</a></p>
                </div>
            </div>
        </div>

        @include('auth.partials.footer')
    </div>
</x-login-layout>