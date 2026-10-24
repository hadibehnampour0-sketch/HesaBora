<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
        } catch (error) {}
    </script>
    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="/images/logo.svg">
    <link rel="icon" type="image/png" href="/images/logo.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 text-base-content">
    <main class="mx-auto flex min-h-screen flex-col p-0">
        {{ $slot }}
    </main>
</body>

</html>
