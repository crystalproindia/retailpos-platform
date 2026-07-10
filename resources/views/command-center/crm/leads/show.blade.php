@extends('layouts.admin')

@section('title', $lead->title)
@section('page-title', $lead->title)

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Leads</span><span>/</span><span>{{ $lead->id }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ $lead->status?->name }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $lead->title }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? 'Unlinked lead' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('crm.leads.edit', $lead) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>
                    @if (! $lead->converted_at)
                        <form method="POST" action="{{ route('crm.leads.convert', $lead) }}">
                            @csrf
                            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Convert</button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Lead Details</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        'Priority' => $lead->priority?->label(),
                        'Source' => $lead->source?->name,
                        'Owner' => $lead->assignedUser?->name,
                        'Email' => $lead->email,
                        'Phone' => $lead->phone,
                        'Expected Value' => '₹'.number_format((float) $lead->expected_value, 0),
                        'Next Follow-up' => $lead->next_follow_up_at?->format('d M Y, h:i A'),
                        'Converted' => $lead->converted_at?->format('d M Y, h:i A') ?? 'No',
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value ?? 'N/A' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Timeline</h2>
                <form method="POST" action="{{ route('crm.leads.notes.store', $lead) }}" class="mt-5">
                    @csrf
                    <textarea name="body" rows="3" required placeholder="Add a note" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                    <button class="mt-3 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Add note</button>
                </form>
                <div class="mt-6 space-y-3">
                    @foreach ($lead->notes as $note)
                        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="text-sm text-slate-700 dark:text-slate-200">{{ $note->body }}</p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $note->user?->name }} · {{ $note->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                    @endforeach
                    @foreach ($lead->activities as $activity)
                        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $activity->subject }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $activity->type?->label() }} · {{ $activity->scheduled_at?->format('d M Y, h:i A') ?? 'Not scheduled' }}</p>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>
    </div>
@endsection
