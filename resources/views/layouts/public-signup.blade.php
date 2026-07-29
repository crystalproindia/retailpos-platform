<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Start Free RetailPOS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-950 antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(0,0.95fr)_minmax(520px,1.05fr)]">
        <section class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute left-0 top-0 h-1 w-48 bg-teal-400"></div>
            <a href="{{ route('saas.public-signup.show') }}" class="relative flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg bg-teal-400 text-sm font-bold text-slate-950">RP</span><span><span class="block text-sm font-semibold uppercase tracking-[0.18em] text-teal-200">RetailPOS</span><span class="block text-sm text-slate-400">Free 365</span></span></a>
            <div class="relative max-w-xl"><p class="text-4xl font-semibold leading-tight tracking-normal">Start your free Retail POS in about one minute.</p><p class="mt-6 text-base leading-7 text-slate-300">No credit card required. Get GST-ready billing, products, customers, and basic inventory free for 365 days.</p><ul class="mt-9 space-y-4 text-sm text-slate-200"><li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-teal-400 text-xs font-bold text-slate-950">✓</span>25 finalized invoices every month</li><li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-teal-400 text-xs font-bold text-slate-950">✓</span>One outlet and one user included</li><li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-teal-400 text-xs font-bold text-slate-950">✓</span>Built for everyday retail work</li></ul></div>
            <p class="relative text-sm text-slate-400">Already using RetailPOS? <a href="{{ route('login') }}" class="font-semibold text-white hover:text-teal-200">Sign in</a></p>
        </section>
        <section class="flex min-h-screen items-start justify-center bg-slate-50 px-4 py-8 sm:px-6 sm:py-12 lg:items-center lg:px-10">
            <div class="w-full max-w-2xl"><a href="{{ route('saas.public-signup.show') }}" class="mb-8 flex items-center gap-3 lg:hidden"><span class="grid size-10 place-items-center rounded-lg bg-slate-950 text-sm font-bold text-white">RP</span><span><span class="block font-semibold text-slate-950">RetailPOS</span><span class="block text-sm text-slate-500">Free 365</span></span></a><div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-8">@yield('content')</div></div>
        </section>
    </main>
</body>
</html>
