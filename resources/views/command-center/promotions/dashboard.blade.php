@extends('layouts.admin')

@section('title', 'Promotions')
@section('page-title', 'Promotions')
@section('breadcrumbs')
    <span>/</span><span>Promotions</span>
@endsection

@section('content')
    @include('command-center.promotions.partials.nav')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div><p class="text-sm text-slate-500 dark:text-slate-400">Manage discount rules, campaigns, coupons, and pre-POS cart testing.</p></div>
        @can('promotions.rules.create')<a href="{{ route('promotions.rules.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-teal-700">New promotion rule</a>@endcan
    </div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($dashboard['cards'] as $card)
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p><p class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">{{ $card['value'] }}</p></div>
        @endforeach
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="xl:col-span-2 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Top active offers</h2><a href="{{ route('promotions.rules.index') }}" class="text-sm font-medium text-teal-700 dark:text-teal-300">All rules</a></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($dashboard['topActiveOffers'] as $rule)
                    <a href="{{ route('promotions.rules.show', $rule) }}" class="flex items-center justify-between px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800"><div><p class="font-medium text-slate-950 dark:text-white">{{ $rule->name }}</p><p class="text-xs text-slate-500">{{ str($rule->promotion_type->value)->headline() }} · Priority {{ $rule->priority }}</p></div><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200">Active</span></a>
                @empty <p class="px-5 py-8 text-sm text-slate-500">No active promotions yet.</p> @endforelse
            </div>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold text-slate-950 dark:text-white">Promotion signals</h2>
            <dl class="mt-5 space-y-4 text-sm"><div class="flex justify-between"><dt class="text-slate-500">Campaigns</dt><dd class="font-semibold text-slate-950 dark:text-white">{{ $dashboard['campaignCount'] }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Discount recorded</dt><dd class="font-semibold text-slate-950 dark:text-white">₹{{ number_format($dashboard['totalDiscountSimulated'], 2) }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Coupon usage</dt><dd class="font-semibold text-slate-950 dark:text-white">{{ $dashboard['couponUsage'] }}</dd></div></dl>
            @can('promotions.simulator.view')<a href="{{ route('promotions.simulator.index') }}" class="mt-6 block rounded-lg border border-teal-200 px-4 py-3 text-center text-sm font-semibold text-teal-700 hover:bg-teal-50 dark:border-teal-900 dark:text-teal-300 dark:hover:bg-teal-950">Open simulator</a>@endcan
        </section>
    </div>
@endsection
