@php($jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $site['locale']) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title data-pacms-head>{{ $seo['title'] }}</title>
    @if (! empty($seo['description']))
        <meta name="description" content="{{ $seo['description'] }}" data-pacms-head>
    @endif
    <meta name="robots" content="{{ $seo['robots'] }}" data-pacms-head>
    @if (! empty($seo['canonical']))
        <link rel="canonical" href="{{ $seo['canonical'] }}" data-pacms-head>
        <meta property="og:url" content="{{ $seo['canonical'] }}" data-pacms-head>
    @endif
    <meta property="og:site_name" content="{{ $site['name'] }}">
    <meta property="og:type" content="{{ $seo['og']['type'] ?? 'website' }}" data-pacms-head>
    <meta property="og:title" content="{{ $seo['og']['title'] ?? $seo['title'] }}" data-pacms-head>
    @if (! empty($seo['og']['description']))
        <meta property="og:description" content="{{ $seo['og']['description'] }}" data-pacms-head>
    @endif
    @if (! empty($seo['og']['image']))
        <meta property="og:image" content="{{ $seo['og']['image'] }}" data-pacms-head>
        @if (! empty($seo['og']['image_alt']))
            <meta property="og:image:alt" content="{{ $seo['og']['image_alt'] }}" data-pacms-head>
        @endif
    @endif
    <meta name="twitter:card" content="{{ $seo['twitter']['card'] ?? 'summary' }}" data-pacms-head>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="{{ $site['theme']['stylesheet'] }}">
    @viteReactRefresh
    @vite(['resources/scss/public.scss', 'resources/js/public/main.jsx'])

    @foreach ($seo['json_ld'] ?? [] as $schema)
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">{!! json_encode($schema, $jsonFlags) !!}</script>
    @endforeach
</head>
<body class="pa-public">
    <div id="app">
        <noscript>
            <div class="container py-5">
                <h1>{{ $initial['page']['title'] ?? $site['name'] }}</h1>
                @if (! empty($initial['page']['excerpt']))
                    <p>{{ $initial['page']['excerpt'] }}</p>
                @endif
                <p>This website works best with JavaScript enabled.</p>
            </div>
        </noscript>
    </div>
    {{-- Initial data for the SPA (read, never executed). Encoded so "</script>" cannot break out. --}}
    <script id="pacms-initial" type="application/json">{!! json_encode($initial, $jsonFlags) !!}</script>
</body>
</html>
