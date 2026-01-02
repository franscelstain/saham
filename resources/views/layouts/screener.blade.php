<!doctype html>
<html lang="id" data-theme="ajaib">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Screener' }}</title>

        <link rel="stylesheet" href="{{ mix('/css/app.css') }}">
        @stack('head')
    </head>
    <body class="bg-base-200 text-base-content">
        @yield('content')
        @stack('scripts')
    </body>
</html>