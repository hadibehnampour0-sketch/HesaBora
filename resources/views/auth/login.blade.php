<x-login-layout>
    <div class="login-bg flex-1 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 border border-base-300 bg-base-100 shadow-xl">
                <div class="flex flex-col items-center mb-6">
                    <img src="/images/logo.svg" alt="{{ config('app.name') }}" width="72" class="mb-2">
                    <h1 class="font-bold text-2xl">دخل</h1>
                    <span class="text-gray-500">{{ config('app.name') }}</span>
                </div>

                <x-show-message-bags />

                <form method="POST" action="{{ route('login') }}" autocomplete="on">
                    @csrf
                    <x-text-input class="mt-1 w-full max-w-xs" :title="__('Username')" input_name="username" id_input="username"
                        type="text" :placeholder="__('Enter your username')" autocomplete="username" required autofocus />
                    <x-text-input class="mt-1 w-full max-w-xs" :title="__('Login Password')" input_name="password" id_input="password"
                        type="password" :placeholder="__('Enter your password')" autocomplete="current-password" required />
                    <button type="submit" class="btn btn-primary w-full py-2 px-8 rounded mt-4">{{ __('Login') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-login-layout>