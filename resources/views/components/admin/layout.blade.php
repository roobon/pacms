@props(['title' => null])
@php($navigation = \App\Support\Admin\AdminNavigation::for(auth()->user()))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}Admin · {{ app(\App\Services\Settings\SettingsService::class)->get('site', 'name') }}</title>
    @vite(['resources/scss/admin.scss', 'resources/js/admin/app.js'])
</head>
<body class="pa-admin">
    <a class="visually-hidden-focusable pa-skip-link" href="#main">Skip to main content</a>

    <div class="pa-admin-shell">
        <aside class="pa-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="adminSidebar" aria-label="Admin navigation">
            <div class="pa-sidebar__brand">
                <a href="{{ route('admin.dashboard') }}" class="pa-sidebar__logo">
                    <span class="pa-sidebar__mark" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    <span>Probha Aurora <small>CMS</small></span>
                </a>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close navigation"></button>
            </div>
            <nav class="pa-sidebar__nav">
                @foreach ($navigation as $section)
                    @if ($section['label'])
                        <p class="pa-sidebar__heading">{{ $section['label'] }}</p>
                    @endif
                    <ul class="list-unstyled mb-3">
                        @foreach ($section['items'] as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <li>
                                <a href="{{ route($item['route']) }}" @class(['pa-sidebar__link', 'is-active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>
            <div class="pa-sidebar__footer">PACMS v{{ config('pacms.version') }}</div>
        </aside>

        <div class="pa-admin-main">
            <header class="pa-topbar">
                <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open navigation">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a href="{{ url('/') }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> View site<span class="visually-hidden"> (opens in new tab)</span>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-sm pa-user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="pa-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('admin.account.profile') }}"><i class="bi bi-person me-2" aria-hidden="true"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.account.security') }}"><i class="bi bi-shield-check me-2" aria-hidden="true"></i>Security</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Sign out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main id="main" class="pa-content" tabindex="-1">
                <x-admin.flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
