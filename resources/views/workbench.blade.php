<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Anima - Webhook Interceptor & Replay Studio</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <script>
        (function() {
            var theme = localStorage.getItem('anima-theme');
            if (theme === 'light') {
                document.documentElement.classList.remove('dark');
            } else {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @php
        $path = config('anima.path', 'anima');
        $cssUrl = file_exists(public_path('vendor/anima/app.css'))
            ? asset('vendor/anima/app.css')
            : url("{$path}/assets/app.css");
        $jsUrl = file_exists(public_path('vendor/anima/app.js'))
            ? asset('vendor/anima/app.js')
            : url("{$path}/assets/app.js");
    @endphp

    <link rel="stylesheet" href="{{ $cssUrl }}">

    <script>
        window.Anima = {!! json_encode([
            'path' => config('anima.path', 'anima'),
            'csrfToken' => csrf_token(),
            'storageDriver' => config('anima.storage.driver', 'database'),
        ]) !!};
    </script>
</head>
<body class="bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased font-sans transition-colors duration-150">
    <div id="app"></div>

    <script type="module" src="{{ $jsUrl }}"></script>
</body>
</html>
