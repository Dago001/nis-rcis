<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Nigeria Immigration Service - ECOWAS Residence Card Portal') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=1.1">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=1.1">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-32x32.png') }}?v=1.1">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon.png') }}?v=1.1">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=1.1">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}?v=1.1">

    @php
        $turnstileSiteKey = config('services.turnstile.site_key') ?: '0x4AAAAAAFpWM5olq43To2dX';
    @endphp
    @if ($turnstileSiteKey)
        <script>
            window.__TURNSTILE_SITE_KEY__ = @json($turnstileSiteKey);
        </script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    @endif

    @if (app()->environment('local') && file_exists(public_path('hot')))
        @viteReactRefresh
    @endif
    @vite(['resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="h-full font-sans antialiased text-slate-800 bg-slate-50 flex flex-col">
    @inertia
</body>
</html>
