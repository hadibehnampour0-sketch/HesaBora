<x-login-layout>
    <div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-tr from-base-200 via-base-100 to-base-200/50">
        <div class="w-full max-w-md">
            <div class="card p-8 rounded-3xl border border-base-300 bg-base-100 shadow-2xl shadow-slate-900/10 transition-all">
                <div class="flex flex-col items-center mb-6 text-center">
                    <div class="w-20 h-20 rounded-2xl bg-[#090D16] p-2.5 shadow-xl shadow-cyan-500/20 border border-slate-800 mb-3 flex items-center justify-center">
                        <img src="/images/neptune-logo.svg" alt="{{ config('app.name') }}" class="w-full h-full object-contain drop-shadow-md">
                    </div>
                    <h1 class="font-black text-2xl tracking-tight text-base-content">{{ __('سامانه مالی و حسابداری نپتون') }}</h1>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold mt-2">
                        <span>✨</span> {{ __('سیستم‌عامل جامع مالی و هوشمند نپتون') }}
                    </span>
                </div>

                <x-show-message-bags />

                @if ($errors->any())
                    <div class="alert alert-error text-xs rounded-2xl mb-4 font-medium flex items-center gap-2 shadow-sm">
                        <svg class="w-5 h-5 shrink-0 text-error-content" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <div class="space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" autocomplete="on" class="space-y-4">
                    @csrf
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-base-content/70">{{ __('نام کاربری') }}</label>
                        <input type="text"
                               name="username"
                               id="username"
                               value="{{ old('username') }}"
                               placeholder="{{ __('نام کاربری خود را وارد کنید') }}"
                               autocomplete="username"
                               required
                               autofocus
                               class="input input-bordered w-full rounded-xl text-sm focus:outline-primary bg-base-200/40" />
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-base-content/70">{{ __('کلمه عبور ورود') }}</label>
                        <input type="password"
                               name="password"
                               id="password"
                               placeholder="{{ __('کلمه عبور خود را وارد کنید') }}"
                               autocomplete="current-password"
                               required
                               class="input input-bordered w-full rounded-xl text-sm focus:outline-primary bg-base-200/40" />
                    </div>

                    <button type="submit" class="btn btn-primary w-full rounded-xl font-bold shadow-lg shadow-primary/25 mt-4 transition-all">
                        {{ __('ورود به سامانه') }}
                    </button>
                </form>

                <div class="mt-4 flex items-center justify-between text-xs text-base-content/70 px-1">
                    <a href="{{ route('landing') }}" class="hover:text-primary transition flex items-center gap-1 font-semibold">
                        <span>🌐</span> <span>{{ __('معرفی نرم‌افزار و ۱۶ صنف') }}</span>
                    </a>
                    <a href="{{ route('pricing') }}" class="hover:text-primary transition flex items-center gap-1 font-semibold text-primary">
                        <span>⚡</span> <span>{{ __('تعرفه‌ها و خرید لایسنس') }}</span>
                    </a>
                </div>

                <div class="mt-6 pt-4 border-t border-base-200 text-center text-xs text-base-content/50">
                    {{ __('نسخه :version', ['version' => config('app.version', '1.0')]) }} • {{ __('دوبل و چندشرکتی') }}
                </div>
            </div>
        </div>
    </div>
</x-login-layout>
