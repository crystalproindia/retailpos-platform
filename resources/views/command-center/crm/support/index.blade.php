@extends('layouts.admin')

@section('title', 'Support Desk')
@section('page-title', 'Support Desk')

@section('content')
    @php
        $priorityClasses = ['urgent' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/70 dark:text-rose-200', 'high' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200', 'normal' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-200', 'low' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'];
        $statusClasses = ['new' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200', 'open' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-200', 'in_progress' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-200', 'waiting_for_customer' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200', 'waiting_for_internal_team' => 'bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-200', 'resolved' => 'bg-teal-100 text-teal-800 dark:bg-teal-950/70 dark:text-teal-200', 'closed' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200', 'reopened' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/70 dark:text-orange-200'];
        $attentionFilters = [
            ['label' => 'All open', 'value' => $metrics['open'], 'params' => ['unresolved' => 1], 'tone' => 'text-slate-700 dark:text-slate-200'],
            ['label' => 'Urgent', 'value' => $metrics['urgent'], 'params' => ['priority' => 'urgent', 'unresolved' => 1], 'tone' => 'text-rose-700 dark:text-rose-300'],
            ['label' => 'Overdue', 'value' => $metrics['overdue'], 'params' => ['overdue' => 1], 'tone' => 'text-amber-700 dark:text-amber-300'],
            ['label' => 'Waiting on us', 'value' => $metrics['waiting_for_internal_team'], 'params' => ['status' => 'waiting_for_internal_team'], 'tone' => 'text-violet-700 dark:text-violet-300'],
        ];
    @endphp

    <div class="space-y-6 support-desk-shell">
        @include('command-center.crm.partials.nav')

        <section class="support-desk-header border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-2 text-sm font-semibold text-sky-700 dark:text-sky-300"><span class="support-live-dot"></span> Support operations</div>
                    <h1 class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white">A calmer way to run customer support.</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Start with what needs attention, keep every handoff visible, and make the next customer response obvious.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <div class="support-desk-health"><span class="support-live-dot"></span><span><strong>SLA monitor</strong><small>Active</small></span></div>
                    @can('crm.support.create')<a href="{{ route('crm.support.tickets.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 active:translate-y-px dark:bg-teal-300 dark:text-slate-950">Create Ticket</a>@endcan
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Support overview">
            @foreach ([['Open', $metrics['open'], 'All unresolved customer requests.', 'bg-slate-50 dark:bg-slate-950'], ['Urgent', $metrics['urgent'], 'Escalate these first.', 'bg-rose-50 dark:bg-rose-950/30'], ['Overdue', $metrics['overdue'], 'SLA needs immediate attention.', 'bg-amber-50 dark:bg-amber-950/30'], ['Waiting for Customer', $metrics['waiting_for_customer'], 'A customer response is needed.', 'bg-sky-50 dark:bg-sky-950/30'], ['Resolved This Month', $metrics['resolved_this_month'], 'Completed service outcomes.', 'bg-teal-50 dark:bg-teal-950/30']] as [$label, $value, $helper, $tone])
                <a href="{{ route('crm.support.tickets.index', $label === 'Open' ? ['unresolved' => 1] : ($label === 'Urgent' ? ['priority' => 'urgent', 'unresolved' => 1] : ($label === 'Overdue' ? ['overdue' => 1] : ($label === 'Waiting for Customer' ? ['status' => 'waiting_for_customer'] : ['status' => 'resolved'])))) }}" class="support-metric-card rounded-lg border border-slate-200 p-4 shadow-sm transition dark:border-slate-800 {{ $tone }}"><div class="flex items-start justify-between gap-3"><div><p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $label }}</p><p class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">{{ $value }}</p></div><span class="mt-1 size-2 rounded-full {{ $label === 'Urgent' ? 'bg-rose-500' : ($label === 'Overdue' ? 'bg-amber-500' : ($label === 'Resolved This Month' ? 'bg-teal-500' : 'bg-sky-500')) }}"></span></div><p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $helper }}</p></a>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[15rem_minmax(0,1fr)]">
            <aside class="space-y-4">
                <div class="support-filter-rail border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Triage queue</h2><a href="{{ route('crm.support.tickets.index') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-900 dark:text-sky-300">Reset</a></div>
                    <div class="mt-3 space-y-1">@foreach ($attentionFilters as $filter)<a href="{{ route('crm.support.tickets.index', $filter['params']) }}" class="support-filter-link {{ request()->fullUrlIs(route('crm.support.tickets.index', $filter['params'])) ? 'is-active' : '' }}"><span>{{ $filter['label'] }}</span><span class="{{ $filter['tone'] }}">{{ $filter['value'] }}</span></a>@endforeach</div>
                    <div class="mt-5 border-t border-slate-100 pt-4 dark:border-slate-800"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Team signal</p><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $metrics['waiting_for_internal_team'] ? $metrics['waiting_for_internal_team'].' ticket'.($metrics['waiting_for_internal_team'] === 1 ? ' is' : 's are').' waiting on an internal handoff.' : 'No customer request is waiting on an internal handoff.' }}</p></div>
                </div>
            </aside>

            <div class="min-w-0 space-y-4">
                <form method="GET" class="support-search-panel border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-3 lg:flex-row"><div class="min-w-0 flex-1"><label class="sr-only" for="support-search">Search tickets</label><input id="support-search" name="search" value="{{ request('search') }}" placeholder="Search by ticket, customer, email, or phone" class="w-full"></div><div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:flex"><select name="status"><option value="">Any status</option>@foreach (\App\Enums\Crm\SupportTicketStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select><select name="priority"><option value="">Any priority</option>@foreach (\App\Enums\Crm\SupportTicketPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select><select name="assigned_to"><option value="">Any owner</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected((string) request('assigned_to') === (string) $owner->id)>{{ $owner->name }}</option>@endforeach</select><select name="sort"><option value="updated_at" @selected(request('sort', 'updated_at') === 'updated_at')>Recently updated</option><option value="priority" @selected(request('sort') === 'priority')>Priority</option><option value="due_at" @selected(request('sort') === 'due_at')>SLA due date</option><option value="created_at" @selected(request('sort') === 'created_at')>Newest first</option></select></div><button class="min-h-11 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 active:translate-y-px dark:bg-teal-300 dark:text-slate-950">Filter</button></div>
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs"><label class="inline-flex items-center gap-2 font-semibold text-slate-600 dark:text-slate-300"><input type="checkbox" name="overdue" value="1" @checked(request('overdue'))> Overdue only</label><select name="category" class="min-h-8 !rounded-md !py-1 text-xs"><option value="">Any category</option>@foreach (\App\Enums\Crm\SupportTicketCategory::cases() as $category)<option value="{{ $category->value }}" @selected(request('category') === $category->value)>{{ $category->label() }}</option>@endforeach</select><select name="source" class="min-h-8 !rounded-md !py-1 text-xs"><option value="">Any source</option>@foreach (\App\Enums\Crm\SupportTicketSource::cases() as $source)<option value="{{ $source->value }}" @selected(request('source') === $source->value)>{{ $source->label() }}</option>@endforeach</select></div>
                </form>

                <section class="support-inbox overflow-hidden border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center dark:border-slate-800"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Ticket inbox</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $tickets->total() }} ticket{{ $tickets->total() === 1 ? '' : 's' }} in this view.</p></div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Sorted by {{ request('sort', 'updated_at') === 'updated_at' ? 'recent activity' : str(request('sort'))->replace('_', ' ')->headline() }}</p></div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($tickets as $ticket)
                            @php
                                $isOverdue = $ticket->due_at?->isPast() && $ticket->status->isOpen();
                                $customerName = $ticket->customer?->company_name ?? $ticket->reported_by_name ?? 'Unlinked request';
                                $initial = str($customerName)->substr(0, 1)->upper();
                            @endphp
                            <a href="{{ route('crm.support.tickets.show', $ticket) }}" class="support-inbox-row {{ $ticket->priority->value }} block px-4 py-4 sm:px-5"><div class="flex gap-3 sm:gap-4"><div class="support-customer-avatar {{ $ticket->priority->value }}">{{ $initial }}</div><div class="min-w-0 flex-1"><div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-[0.7rem] font-semibold text-slate-400">{{ $ticket->ticket_number }}</span><span class="support-status-badge inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$ticket->status->value] }}">{{ $ticket->status->label() }}</span><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClasses[$ticket->priority->value] }}">{{ $ticket->priority->label() }}</span></div><p class="mt-2 truncate text-base font-semibold text-slate-950 dark:text-white">{{ $ticket->subject }}</p><p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ $customerName }} · {{ $ticket->category->label() }} via {{ $ticket->source->label() }}</p></div><div class="flex min-w-0 gap-5 xl:justify-end"><div class="hidden text-right lg:block"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Owner</p><p class="mt-1 truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $ticket->assignee?->name ?? 'Unassigned' }}</p></div><div class="text-right"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">SLA</p><p class="mt-1 text-sm font-semibold {{ $isOverdue ? 'text-rose-700 dark:text-rose-300' : 'text-slate-700 dark:text-slate-200' }}">{{ $ticket->due_at ? ($isOverdue ? 'Overdue' : $ticket->due_at->diffForHumans()) : 'Not set' }}</p></div></div></div><div class="mt-3 flex items-center justify-between gap-3"><p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $ticket->reported_by_phone ?? $ticket->reported_by_email ?? 'No customer contact detail' }}</p><span class="shrink-0 text-xs font-medium text-slate-400">Updated {{ $ticket->updated_at->diffForHumans() }}</span></div></div></div></a>
                        @empty
                            <div class="px-5 py-16 text-center"><div class="mx-auto grid size-12 place-items-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300"><x-icon name="activity" class="size-6" /></div><p class="mt-4 text-base font-semibold text-slate-900 dark:text-white">Your support queue is clear</p><p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">There are no tickets for these filters. Adjust the queue or create a customer request when one arrives.</p>@can('crm.support.create')<a href="{{ route('crm.support.tickets.create') }}" class="mt-5 inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Create Ticket</a>@endcan</div>
                        @endforelse
                    </div>
                </section>
                {{ $tickets->links() }}
            </div>
        </section>
    </div>
@endsection
