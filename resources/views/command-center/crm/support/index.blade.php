@extends('layouts.admin')

@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
    @php
        $priorityClasses = ['urgent' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/70 dark:text-rose-200', 'high' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200', 'normal' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-200', 'low' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'];
        $statusClasses = ['new' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200', 'open' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-200', 'in_progress' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-200', 'waiting_for_customer' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200', 'waiting_for_internal_team' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-200', 'resolved' => 'bg-teal-100 text-teal-800 dark:bg-teal-950/70 dark:text-teal-200', 'closed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200', 'reopened' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/70 dark:text-orange-200'];
    @endphp
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-sky-700 dark:text-sky-300">Customer care workspace</p><h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Support Tickets</h1><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Track requests, ownership, customer-safe replies, and SLA commitments in one place.</p></div>
            @can('crm.support.create')<a href="{{ route('crm.support.tickets.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 active:translate-y-px dark:bg-teal-300 dark:text-slate-950">Create Ticket</a>@endcan
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([['Open Tickets', $metrics['open'], 'bg-slate-50 dark:bg-slate-950'], ['Urgent', $metrics['urgent'], 'bg-rose-50 dark:bg-rose-950/30'], ['Overdue', $metrics['overdue'], 'bg-amber-50 dark:bg-amber-950/30'], ['Waiting for Customer', $metrics['waiting_for_customer'], 'bg-sky-50 dark:bg-sky-950/30'], ['Resolved This Month', $metrics['resolved_this_month'], 'bg-teal-50 dark:bg-teal-950/30']] as [$label, $value, $tone])
                <a href="{{ route('crm.support.tickets.index', $label === 'Overdue' ? ['overdue' => 1] : ($label === 'Open Tickets' ? ['unresolved' => 1] : [])) }}" class="support-summary-card rounded-lg border border-slate-200 p-5 shadow-sm transition dark:border-slate-800 {{ $tone }}"><p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $value }}</p></a>
            @endforeach
        </section>

        <form method="GET" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <input name="search" value="{{ request('search') }}" placeholder="Search ticket, customer, email or phone" class="xl:col-span-2">
                <select name="status"><option value="">All statuses</option>@foreach (\App\Enums\Crm\SupportTicketStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                <select name="priority"><option value="">All priorities</option>@foreach (\App\Enums\Crm\SupportTicketPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select>
                <select name="assigned_to"><option value="">All owners</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected((string) request('assigned_to') === (string) $owner->id)>{{ $owner->name }}</option>@endforeach</select>
                <select name="category"><option value="">All categories</option>@foreach (\App\Enums\Crm\SupportTicketCategory::cases() as $category)<option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>@endforeach</select>
                <select name="source"><option value="">All sources</option>@foreach (\App\Enums\Crm\SupportTicketSource::cases() as $source)<option value="{{ $source->value }}" @selected(request('source') === $source->value)>{{ $source->label() }}</option>@endforeach</select>
                <select name="sort"><option value="updated_at" @selected(request('sort', 'updated_at') === 'updated_at')>Recently updated</option><option value="priority" @selected(request('sort') === 'priority')>Priority</option><option value="due_at" @selected(request('sort') === 'due_at')>SLA due date</option><option value="created_at" @selected(request('sort') === 'created_at')>Newest created</option></select>
                <label class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))> Overdue only</label>
                <div class="flex gap-2"><button class="min-h-11 flex-1 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 active:translate-y-px dark:bg-teal-300 dark:text-slate-950">Apply</button><a href="{{ route('crm.support.tickets.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Reset</a></div>
            </div>
        </form>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="hidden grid-cols-[0.8fr_2fr_1.25fr_1fr_1fr_0.8fr] gap-4 border-b border-slate-200 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 lg:grid"><span>Ticket</span><span>Request</span><span>Customer</span><span>Status</span><span>Owner / SLA</span><span>Updated</span></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($tickets as $ticket)
                    <a href="{{ route('crm.support.tickets.show', $ticket) }}" class="support-ticket-row block px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <div class="grid gap-3 lg:grid-cols-[0.8fr_2fr_1.25fr_1fr_1fr_0.8fr] lg:items-center lg:gap-4"><div><p class="text-xs font-semibold text-slate-500">{{ $ticket->ticket_number }}</p><span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClasses[$ticket->priority->value] }}">{{ $ticket->priority->label() }}</span></div><div><p class="font-semibold text-slate-950 dark:text-white">{{ $ticket->subject }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $ticket->category->label() }} · {{ $ticket->source->label() }}</p></div><div><p class="font-medium text-slate-800 dark:text-slate-100">{{ $ticket->customer?->company_name ?? $ticket->reported_by_name ?? 'Unlinked request' }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $ticket->reported_by_phone ?? $ticket->reported_by_email ?? 'No contact detail' }}</p></div><div><span class="support-status-badge inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$ticket->status->value] }}">{{ $ticket->status->label() }}</span></div><div><p class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ $ticket->assignee?->name ?? 'Unassigned' }}</p><p class="mt-1 text-xs {{ $ticket->due_at?->isPast() && $ticket->status->isOpen() ? 'font-semibold text-rose-700 dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $ticket->due_at ? 'Due '.$ticket->due_at->format('d M, h:i A') : 'SLA not set' }}</p></div><p class="text-sm text-slate-500 dark:text-slate-400">{{ $ticket->updated_at->diffForHumans() }}</p></div>
                    </a>
                @empty
                    <div class="px-5 py-16 text-center"><p class="text-base font-semibold text-slate-900 dark:text-white">No support tickets found</p><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create a ticket or adjust the filters to see customer requests.</p>@can('crm.support.create')<a href="{{ route('crm.support.tickets.create') }}" class="mt-5 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Create Ticket</a>@endcan</div>
                @endforelse
            </div>
        </section>
        {{ $tickets->links() }}
    </div>
@endsection
