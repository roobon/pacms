<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $site['locale']) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-pacms-head>{{ $seo['title'] }}</title>
    @if ($seo['description'])
        <meta name="description" content="{{ $seo['description'] }}">
    @endif
    <meta name="robots" content="{{ $seo['robots'] }}" data-pacms-head>
    @if ($seo['canonical'])
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
    @endif
    <meta property="og:site_name" content="{{ $site['name'] }}">
    <meta property="og:type" content="{{ $seo['og_type'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    @if ($seo['description'])
        <meta property="og:description" content="{{ $seo['description'] }}">
    @endif
    <meta name="twitter:card" content="summary">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ $site['theme']['stylesheet'] }}">
    @viteReactRefresh
    @vite(['resources/scss/public.scss', 'resources/js/public/main.jsx'])

    @if ($seo['json_ld'])
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($seo['json_ld'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
</head>
<body class="pa-public">
    <div id="app">
        <noscript>
            <div class="container py-5">
                <h1>{{ $site['name'] }}</h1>
                <p>This website works best with JavaScript enabled.</p>
            </div>
        </noscript>
    </div>
    {{-- Initial data for the SPA (read, never executed). Encoded so "</script>" cannot break out. --}}
    <script id="pacms-initial" type="application/json">{!! json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
</body>
</html>
