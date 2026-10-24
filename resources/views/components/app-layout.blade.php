@props([
    'title' => config('app.name'),
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
            var savedUiTheme = localStorage.getItem('ui_theme') || '{{ session('dakhl_ui_theme', request()->cookie('dakhl_ui_theme', 'flow')) }}';
            document.documentElement.setAttribute('data-ui-theme', savedUiTheme);
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.setAttribute('data-ui-theme', 'flow');
        }
    </script>
    <title>{{ $title }}</title>
    <link rel="icon" type="image/svg+xml" href="/images/logo.svg">
    <link rel="icon" type="image/png" href="/images/logo.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen overflow-x-hidden bg-base-200 text-base-content">

    <div class="drawer lg:drawer-open">
        <input id="app-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex min-h-screen min-w-0 flex-col">
            <x-header />

            <main class="mx-auto mt-5 w-full max-w-[1430px] flex-1 px-4 lg:px-6">
                {{ $slot }}
            </main>

            <footer class="mt-8 pb-4 text-center text-xs opacity-60">
                {{ __('Integrated Accounting and Human Resources System') }} {{ __('Version :version', ['version' => config('app.version')]) }}
            </footer>
        </div>

        <div class="drawer-side z-40">
            <label for="app-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
            <x-sidebar />
        </div>
    </div>

    <!-- Global Quick Search Command Palette (Ctrl+K) -->
    <x-command-palette />

    <!-- Global Floating AI Copilot Launcher -->
    <x-floating-copilot />

    @stack('scripts')

    @stack('footer')
</body>

</html>
