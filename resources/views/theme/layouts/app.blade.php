<!doctype html>
@php
    $activeTheme = nexus_theme();
    $themeVariables = nexus_theme_variables();
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $activeTheme['key'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', nexus_setting('store.name', 'ShopKeeprr'))</title>
    <meta name="description" content="@yield('description', nexus_setting('store.tagline', ''))">
    @if(nexus_demo())
        <meta name="robots" content="noindex, nofollow">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://store.shopkeeprr.com/analytics.js"></script>
    <style>
        :root {
            @foreach($themeVariables as $name => $value)
            --{{ $name }}: {!! str_replace(['<', '>'], '', (string) $value) !!};
            @endforeach
        }
        .btn-primary {
            background: var(--brand) !important;
            color: var(--button-ink) !important;
            border-radius: var(--btn-radius) !important;
        }
        .btn-primary:hover {
            background: color-mix(in srgb, var(--brand) 84%, #000) !important;
        }
        .btn-secondary, .btn-dark, .btn-danger, .btn-ghost { border-radius: var(--btn-radius) !important; }
        .text-brand { color: var(--link); }
        .bg-brand { background: var(--brand); }
        .border-brand { border-color: var(--brand); }
        .ring-brand:focus { box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 16%, transparent); border-color: var(--brand); }
        .storefront-body { background: var(--surface); color: var(--ink); font-family: var(--font-sans); }
        .font-display, h1, h2, h3 { font-family: var(--font-display); }
        .theme-muted { color: var(--muted); }
        .theme-link { color: var(--link); }
        .theme-price { color: var(--price); }
        .theme-surface { background: var(--surface); }
        .theme-card { background: var(--card); border-color: var(--line); }
        .theme-line { border-color: var(--line); }
        [data-theme="marketplace"] .rounded-2xl,
        [data-theme="marketplace"] .rounded-3xl,
        [data-theme="auction"] .rounded-2xl,
        [data-theme="auction"] .rounded-3xl { border-radius: var(--card-radius) !important; }
        [data-theme="bazaar"] .rounded-3xl { border-radius: 1rem !important; }
    </style>
    @if(nexus_setting('theme.custom_css'))
        <style>{!! nexus_setting('theme.custom_css') !!}</style>
    @endif
    @if(! empty($builderPreview))
        @include('theme.builder.preview-styles')
    @endif
    @stack('head')
</head>
<body class="storefront-body min-h-screen antialiased">
    @include(nexus_partial('partials.header'))

    @if(session('status'))
        <div class="container-wide pt-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('status') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div class="container-wide pt-4">
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        </div>
    @endif
    @if(session('demo_error'))
        <div class="container-wide pt-4">
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">{{ session('demo_error') }}</div>
        </div>
    @endif

    <main>@yield('content')</main>

    @include(nexus_partial('partials.footer'))
    @stack('scripts')
    @if(! empty($builderPreview))
        @include('theme.builder.preview-tools')
    @endif
</body>
</html>
