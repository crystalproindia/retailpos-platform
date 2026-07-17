<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Customer Portal').' | CrystalPro'</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <a href="{{ isset($portalUser) ? route('portal.dashboard') : route('portal.login') }}" class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-lg bg-teal-500 text-sm font-bold text-slate-950">CP</span><span><span class="block text-sm font-semibold tracking-wide text-slate-950">CrystalPro</span><span class="block text-xs text-slate-500">Customer Portal</span></span></a>
            @isset($portalUser)<div class="flex items-center gap-3"><a href="{{ route('portal.profile') }}" class="hidden text-sm font-medium text-slate-600 hover:text-slate-950 sm:inline">{{ $portalUser->name }}</a><form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Sign out</button></form></div>@endisset
        </div>
        @isset($portalUser)<nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 pb-3 sm:px-6" aria-label="Customer portal"><a href="{{ route('portal.dashboard') }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.dashboard') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100' }}">Overview</a><a href="{{ route('portal.quotations.index') }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.quotations.*') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100' }}">Quotations</a><a href="{{ route('portal.proformas.index') }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.proformas.*') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100' }}">Proformas</a><a href="{{ route('portal.onboarding.index') }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.onboarding.*') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100' }}">Onboarding</a><a href="{{ route('portal.support.index') }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.support.*') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100' }}">Support</a><a href="{{ route('portal.services') }}" class="whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('portal.services*') ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100' }}">Request Services</a></nav>@endisset
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">@if(session('status'))<div class="mb-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-medium text-teal-900">{{ session('status') }}</div>@endif @if(session('error'))<div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">{{ session('error') }}</div>@endif @yield('content')</main>
    <footer class="mx-auto max-w-7xl px-4 py-8 text-xs text-slate-500 sm:px-6">Powered by CrystalPro</footer>
</body>
</html>
