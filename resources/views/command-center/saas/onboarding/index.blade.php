@extends('layouts.admin')

@section('title', 'Tenant Onboarding')
@section('page-title', 'Tenant Onboarding')
@section('breadcrumbs')<span>/</span><span>Platform</span><span>/</span><span>Onboarding</span>@endsection

@section('content')
    @include('command-center.saas.partials.nav')
    <div class="mb-5 flex items-center justify-between"><p class="text-sm text-slate-500">Each submission has an idempotency key so retries do not create duplicate tenants.</p><a href="{{ route('saas.onboarding.create') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Onboard tenant</a></div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800"><tr><th class="px-5 py-3">Tenant</th><th class="px-5 py-3">Plan</th><th class="px-5 py-3">Stage</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Updated</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($onboardings as $onboarding)<tr><td class="px-5 py-4">@if($onboarding->company)<a href="{{ route('saas.tenants.show',$onboarding->company) }}" class="font-medium text-slate-900 dark:text-white">{{ $onboarding->company->name }}</a>@else <span class="text-slate-500">Pending tenant</span> @endif</td><td class="px-5 py-4">{{ $onboarding->plan?->name ?? '—' }}</td><td class="px-5 py-4">{{ str($onboarding->current_stage)->headline() }}</td><td class="px-5 py-4"><x-status-badge :status="$onboarding->status" /></td><td class="px-5 py-4 text-slate-500">{{ $onboarding->updated_at?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No tenant onboarding records yet.</td></tr>@endforelse</tbody></table></div></div><div class="mt-4">{{ $onboardings->links() }}</div>
@endsection
