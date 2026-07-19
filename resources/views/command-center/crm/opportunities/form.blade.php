@extends('layouts.admin')

@section('title', 'Create Opportunity')
@section('page-title', 'Create Opportunity')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @include('command-center.crm.partials.nav')
    <form method="POST" action="{{ route('sales.opportunities.store', $lead) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf
        <div><p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $lead->business_name ?? $lead->title }}</p><h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Create sales opportunity</h1><p class="mt-2 text-sm text-slate-500">Turn this qualified conversation into a tracked commercial opportunity.</p></div>
        <div class="mt-6 grid gap-5 sm:grid-cols-2"><label class="sm:col-span-2 text-sm font-medium">Opportunity title<input name="title" required value="{{ old('title', $lead->title) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label><label class="text-sm font-medium">Stage<select name="stage" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="qualified">Qualified</option><option value="demo_completed">Demo completed</option><option value="proposal_required">Proposal required</option><option value="quotation_sent">Quotation sent</option><option value="negotiation">Negotiation</option></select></label><label class="text-sm font-medium">Expected value<input name="expected_value" type="number" min="0" step="0.01" required value="{{ old('expected_value', $lead->expected_value ?? 0) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label><label class="text-sm font-medium">Currency<input name="currency" maxlength="3" required value="{{ old('currency', $lead->currency ?? 'INR') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label><label class="text-sm font-medium">Probability<input name="probability_percentage" type="number" min="0" max="100" required value="{{ old('probability_percentage', 25) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label><label class="text-sm font-medium">Expected close date<input name="expected_close_date" type="date" value="{{ old('expected_close_date') }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label><label class="sm:col-span-2 text-sm font-medium">Description<textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('description', $lead->description) }}</textarea></label></div>
        <button class="mt-6 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create opportunity</button>
    </form>
</div>
@endsection
