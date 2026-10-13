<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Nigeria Immigration Service - ECOWAS Residence Card Portal') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/nis-logo.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/nis-logo.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/nis-logo.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon.png') }}">

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
