<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Nigeria Immigration Service - ECOWAS Residence Card Portal') }}</title>
    <link rel="icon" href="/favicon.ico">

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
