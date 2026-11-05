<x-platform-layout :title="__('about.about_freeamir')">

    <div class="mx-auto max-w-4xl space-y-5 px-4 py-6">

        {{-- Page Header --}}
        <div>
            <h1 class="text-2xl font-black text-base-content">{{ __('about.about_freeamir') }}</h1>
            <p class="mt-1 text-xs text-base-content/65">{{ __('about.description') }}</p>
        </div>

        <x-show-message-bags />

        {{-- Branding & Ownership Header Banner --}}
        <section class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl overflow-hidden">
            <div class="card-body p-5 flex flex-col items-center gap-4 sm:flex-row sm:items-center sm:gap-6">
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-slate-900 border border-slate-800 p-2 shadow-sm">
                    <img src="/images/logo.svg" alt="Neptune" class="h-10 w-10 object-contain drop-shadow" style="width: 40px; height: 40px;">
                </div>
                <div class="text-center sm:text-start flex-1 min-w-0">
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5">
                        <h2 class="text-xl font-black tracking-tight text-base-content">{{ config('app.name', 'Neptune') }}</h2>
                        <span class="badge badge-primary badge-sm font-bold font-mono">{{ __('about.version') }} {{ $version }}</span>
                        <span @class([
                            'badge badge-sm font-semibold',
                            'badge-warning' => $appEnv === 'local',
                            'badge-info' => $appEnv === 'staging',
                            'badge-success' => $appEnv === 'production',
                        ])>{{ $gcSettings['app_env']['options'][$appEnv] ?? $appEnv }}</span>
                    </div>
                    <p class="text-xs text-base-content/70 mt-1.5 flex items-center justify-center sm:justify-start gap-2">
                        <span class="inline-block size-2 rounded-full bg-emerald-500"></span>
                        <span>{{ __('about.rights_reserved') }} © {{ date('Y') }} — {{ __('about.developed_by') }}: <b>{{ __('about.developer_name') }}</b></span>
                    </p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

            {{-- System Information --}}
            <section class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl">
                <div class="card-body p-5">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-base-content">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary shrink-0" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>{{ __('about.system_information') }}</span>
                    </h2>
                    <div class="divider my-1"></div>
                    <table class="table table-sm text-xs">
                        <tbody>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.debug_mode') }}</td>
                                <td>
                                    @if($debugMode)
                                        <span class="badge badge-warning badge-xs">{{ __('about.enabled') }}</span>
                                    @else
                                        <span class="badge badge-success badge-xs">{{ __('about.disabled') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.database') }}</td>
                                <td>
                                    @if($dbConnected)
                                        <span class="badge badge-success badge-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                            {{ __('about.connected') }}
                                        </span>
                                    @else
                                        <span class="badge badge-error badge-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" style="width: 12px; height: 12px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            {{ __('about.disconnected') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.registration') }}</td>
                                <td>
                                    @if(config('app.registration'))
                                        <span class="badge badge-success badge-xs">{{ __('about.enabled') }}</span>
                                    @else
                                        <span class="badge badge-warning badge-xs">{{ __('about.disabled') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.email_verification') }}</td>
                                <td>
                                    @if(config('app.email_verification'))
                                        <span class="badge badge-success badge-xs">{{ __('about.enabled') }}</span>
                                    @else
                                        <span class="badge badge-warning badge-xs">{{ __('about.disabled') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.database_driver') }}</td>
                                <td><span class="font-mono">{{ $dbDriver }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.php_version') }}</td>
                                <td><span class="font-mono">{{ $phpVersion }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.laravel_version') }}</td>
                                <td><span class="font-mono">{{ $laravelVersion }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.locale') }}</td>
                                <td><span class="font-mono">{{ $gcSettings['app_locale']['options'][$locale] ?? $locale }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.timezone') }}</td>
                                <td><span class="font-mono">{{ $timezone }}</span></td>
                            </tr>
                            <tr>
                                <td class="font-medium opacity-70">{{ __('about.server_os') }}</td>
                                <td><span class="font-mono">{{ $serverOs }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Exclusive License, Ownership & Developer --}}
            <section class="card border border-base-300 bg-base-100 shadow-xs rounded-3xl">
                <div class="card-body p-5 space-y-4">
                    {{-- License Box --}}
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-bold text-base-content">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary shrink-0" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>{{ __('about.license') }}</span>
                        </h2>
                        <div class="divider my-1"></div>
                        <div class="rounded-2xl border border-primary/20 bg-primary/5 p-3.5 text-xs leading-relaxed text-base-content/85">
                            <div class="font-bold text-primary mb-1 flex items-center gap-1.5">
                                <span>🔒</span>
                                <span>{{ __('about.license_title') }}</span>
                            </div>
                            <p class="leading-5">{{ __('about.license_text') }}</p>
                        </div>
                    </div>

                    {{-- Developer & Owner Info --}}
                    <div class="flex items-center gap-3.5 p-3.5 rounded-2xl bg-base-200/50 border border-base-200">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-content font-black text-base shadow-sm">
                            HB
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] text-base-content/60 font-medium">{{ __('about.developed_by') }}</div>
                            <div class="font-black text-sm text-base-content">{{ __('about.developer_name') }}</div>
                            <div class="text-[10px] text-primary font-semibold mt-0.5">مجوز انحصاری توسعه، سفارشی‌سازی و انتشار</div>
                        </div>
                    </div>

                    {{-- Support & Inquiries Section --}}
                    <div class="pt-1">
                        <h3 class="flex items-center gap-2 text-xs font-bold text-base-content mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600 shrink-0" style="width: 16px; height: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>{{ __('about.support') }}</span>
                        </h3>
                        <p class="text-xs text-base-content/65 leading-5">
                            {{ __('about.support_desc') }}
                        </p>
                    </div>
                </div>
            </section>

        </div>

    </div>

</x-platform-layout>
