<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RetailPOS Platform') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-950 antialiased">
        <main class="grid min-h-screen lg:grid-cols-[1fr_520px]">
            <section class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(20,184,166,0.22),transparent_32%),radial-gradient(circle_at_82%_18%,rgba(59,130,246,0.16),transparent_28%)]"></div>
                <div class="relative">
                    <div class="flex items-center gap-3">
                        <div class="grid size-11 place-items-center rounded-lg bg-teal-400 font-semibold text-slate-950">RP</div>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.22em] text-teal-200">RetailPOS</p>
                            <p class="text-sm text-slate-400">Command Center</p>
                        </div>
                    </div>
                </div>
                <div class="relative max-w-xl">
                    <p class="text-4xl font-semibold leading-tight tracking-normal">Enterprise retail operations in one secure workspace.</p>
                    <p class="mt-5 text-base leading-7 text-slate-300">Sales, stock, branches, teams, and customer operations stay organized from the first login.</p>
                </div>
            </section>

            <section class="flex min-h-screen items-center justify-center bg-slate-50 p-6">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <div class="grid size-11 place-items-center rounded-lg bg-slate-950 font-semibold text-white">RP</div>
                        <div>
                            <p class="font-semibold text-slate-950">RetailPOS</p>
                            <p class="text-sm text-slate-500">Command Center</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
                        {{ $slot ?? '' }}
                        @yield('content')
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
