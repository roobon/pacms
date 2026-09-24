@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ app(\App\Services\Settings\SettingsService::class)->get('site', 'name') }}</title>
    @vite(['resources/scss/admin.scss', 'resources/js/admin/app.js'])
</head>
<body class="pa-auth">
<main class="pa-auth__panel" id="main">
    <div class="pa-auth__brand">
        <span class="pa-sidebar__mark" aria-hidden="true"><i class="bi bi-stars"></i></span>
        <span>{{ app(\App\Services\Settings\SettingsService::class)->get('site', 'name') }}</span>
    </div>
    <h1 class="pa-auth__title">{{ $title }}</h1>

    {{-- Human-readable statuses only (e.g. password reset link sent); keyed statuses are handled by each page. --}}
    @if (session('status') && str_contains(session('status'), ' '))
        <div class="alert alert-success pa-alert" role="status">{{ __(session('status')) }}</div>
    @endif

    {{ $slot }}
</main>
</body>
</html>
