<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    {{-- Self-contained so error pages work even when the frontend build is unavailable. --}}
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #F4F7F8; color: #334155;
               font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.6; }
        main { max-width: 34rem; padding: 2rem 1rem; text-align: center; }
        .code { font-size: .875rem; font-weight: 600; letter-spacing: .08em; color: #0A6B66; text-transform: uppercase; }
        h1 { color: #0F1B2D; font-size: 1.875rem; line-height: 1.2; margin: .5rem 0 1rem; }
        a { color: #0A6B66; font-weight: 600; }
        a:focus-visible { outline: 3px solid #1B64D1; outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <p class="code">Error @yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    <p><a href="{{ url('/') }}">Go to the homepage</a></p>
</main>
</body>
</html>
