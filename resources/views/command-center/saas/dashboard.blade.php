@extends('layouts.admin')

@section('title', 'SaaS Administration')
@section('page-title', 'SaaS Administration')
@section('breadcrumbs')<span>/</span><span>Platform</span><span>/</span><span>SaaS</span>@endsection

@section('content')
    @include('command-center.saas.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['Total tenants' => $metrics['tenants'], 'Active subscriptions' => $metrics['active'], 'Trials' => $metrics['trials'], 'Trials ending soon' => $metrics['expiring_trials'], 'Past due' => $metrics['past_due'], 'Suspended' => $metrics['suspended'], 'Renewals due' => $metrics['renewals_due']] as $label => $value)
            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ number_format($value) }}</p>
            </section>
        @endforeach
        <a href="{{ route('saas.billing.index') }}" class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm transition hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-950"><p class="text-xs font-medium uppercase tracking-wide text-amber-800 dark:text-amber-200">Billing outstanding</p><p class="mt-2 text-2xl font-semibold text-amber-950 dark:text-white">INR {{ number_format((float)$metrics['billing_outstanding'], 2) }}</p><p class="mt-1 text-sm text-amber-800 dark:text-amber-200">{{ number_format($metrics['billing_overdue']) }} overdue invoices</p></a>
        <section class="rounded-lg border border-sky-200 bg-sky-50 p-4 shadow-sm dark:border-sky-900 dark:bg-sky-950">
            <p class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300">Revenue readiness</p>
            <p class="mt-2 text-sm text-sky-900 dark:text-sky-100">Provider-neutral estimates are intentionally unavailable until subscription invoices and payments are posted.</p>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="font-semibold text-slate-950 dark:text-white">Recent subscriptions</h2>
                <a href="{{ route('saas.subscriptions.index') }}" class="text-sm font-medium text-sky-700 hover:text-sky-900 dark:text-sky-300">View all</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentSubscriptions as $subscription)
                    <a href="{{ route('saas.tenants.show', $subscription->company) }}" class="block px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800">
                        <div class="flex items-center justify-between gap-3"><span class="font-medium text-slate-900 dark:text-white">{{ $subscription->company?->name }}</span><x-status-badge :status="$subscription->status" /></div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subscription->plan?->name ?? 'Historical plan' }} · {{ $subscription->subscription_number }}</p>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No subscriptions yet.</p>
                @endforelse
            </div>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="font-semibold text-slate-950 dark:text-white">Recent onboarding</h2>
                <a href="{{ route('saas.onboarding.create') }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Onboard tenant</a>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($recentOnboarding as $onboarding)
                    <div class="px-5 py-4"><div class="flex items-center justify-between gap-3"><span class="font-medium text-slate-900 dark:text-white">{{ $onboarding->company?->name ?? 'In progress' }}</span><x-status-badge :status="$onboarding->status" /></div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $onboarding->plan?->name ?? 'Plan pending' }} · {{ str($onboarding->current_stage)->headline() }}</p></div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No tenant onboarding records yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
