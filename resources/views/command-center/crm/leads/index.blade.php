@extends('layouts.admin')

@section('title', request()->boolean('demo_requests') ? 'Demo Requests' : 'CRM Leads')
@section('page-title', request()->boolean('demo_requests') ? 'Demo Requests' : 'CRM Leads')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Leads</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">{{ request()->boolean('demo_requests') ? 'Demo Requests' : 'Leads' }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ request()->boolean('demo_requests') ? 'Review inbound Book Demo enquiries and move each request into an accountable follow-up.' : 'Search, filter, assign, qualify, and manage the lead lifecycle.' }}</p>
                </div>
                <a href="{{ route('crm.leads.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">New lead</a>
            </div>

            <form method="GET" action="{{ route('crm.leads.index') }}" class="mt-5 grid gap-3 lg:grid-cols-[minmax(220px,1fr)_150px_150px_135px_160px_130px_auto]">
                @if (request()->boolean('demo_requests'))
                    <input type="hidden" name="demo_requests" value="1">
                @endif
                <input name="search" value="{{ request('search') }}" placeholder="Search leads" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="status_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}" @selected((int) request('status_id') === $status->id)>{{ $status->name }}</option>
                    @endforeach
                </select>
                <select name="source_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}" @selected((int) request('source_id') === $source->id)>{{ $source->name }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Priority</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </select>
                <select name="assigned_user_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All owners</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) request('assigned_user_id') === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                <select name="trashed" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Active</option>
                    <option value="with" @selected(request('trashed') === 'with')>With trash</option>
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <form method="POST" action="{{ route('crm.leads.bulk') }}" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 md:flex-row md:items-center dark:border-slate-800">
                <select name="action" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="status">Change status</option>
                    <option value="assign">Assign owner</option>
                </select>
                <select name="status_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->id }}">{{ $status->name }}</option>
                    @endforeach
                </select>
                <select name="assigned_user_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Apply</button>
            </div>
            <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-800">
                @forelse ($leads as $lead)
                    <article class="space-y-3 p-4">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" name="ids[]" value="{{ $lead->id }}" class="mt-1 rounded border-slate-300">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('crm.leads.show', $lead) }}" class="font-semibold text-slate-950 dark:text-white">{{ $lead->title }}</a>
                                <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? $lead->email ?? 'No contact details' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $lead->status?->name }}</span>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-100">{{ $lead->priority?->label() }}</span>
                            @if ($lead->source)<span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-900 dark:text-sky-100">{{ $lead->source->name }}</span>@endif
                        </div>
                        <div class="flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
                            <span>{{ $lead->assignedUser?->name ?? 'Unassigned' }}</span>
                            <span>₹{{ number_format((float) $lead->expected_value, 0) }}</span>
                        </div>
                        @if (request()->boolean('demo_requests') && $lead->latestDemoSchedule)
                            <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <span class="font-semibold">{{ $lead->latestDemoSchedule->starts_at?->setTimezone($lead->latestDemoSchedule->timezone)->format('d M, h:i A') }}</span>
                                · {{ $lead->latestDemoSchedule->assignedTo?->name ?? 'Unassigned' }} · {{ $lead->latestDemoSchedule->meeting_mode?->label() }} · {{ $lead->latestDemoSchedule->status?->label() }}
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No CRM leads found.</p>
                @endforelse
            </div>
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3"></th>
                            <th class="px-5 py-3">Lead</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Priority</th>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3">Owner</th>
                            @if (request()->boolean('demo_requests'))<th class="px-5 py-3">Scheduled Demo</th>@endif
                            <th class="px-5 py-3">Value</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($leads as $lead)
                            <tr>
                                <td class="px-5 py-4"><input type="checkbox" name="ids[]" value="{{ $lead->id }}" class="rounded border-slate-300"></td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $lead->title }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? $lead->email ?? 'No contact details' }}</p>
                                </td>
                                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $lead->status?->name }}</span></td>
                                <td class="px-5 py-4"><span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-100">{{ $lead->priority?->label() }}</span></td>
                                <td class="px-5 py-4"><span class="rounded-full bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-900 dark:text-sky-100">{{ $lead->source?->name ?? 'Unclassified' }}</span></td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $lead->assignedUser?->name ?? 'Unassigned' }}</td>
                                @if (request()->boolean('demo_requests'))
                                    <td class="px-5 py-4 text-xs text-slate-600 dark:text-slate-300">
                                        @if ($lead->latestDemoSchedule)
                                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $lead->latestDemoSchedule->starts_at?->setTimezone($lead->latestDemoSchedule->timezone)->format('d M Y, h:i A') }}</p>
                                            <p class="mt-1">{{ $lead->latestDemoSchedule->assignedTo?->name ?? 'Unassigned' }} · {{ $lead->latestDemoSchedule->meeting_mode?->label() }}</p>
                                            <p class="mt-1">{{ $lead->latestDemoSchedule->status?->label() }}</p>
                                        @else
                                            Not scheduled
                                        @endif
                                    </td>
                                @endif
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">₹{{ number_format((float) $lead->expected_value, 0) }}</td>
                                <td class="px-5 py-4 text-right">
                                    @if ($lead->trashed())
                                        <button formaction="{{ route('crm.leads.restore', $lead->id) }}" formmethod="POST" class="text-sm font-semibold text-teal-700 dark:text-teal-300">Restore</button>
                                    @else
                                        <a href="{{ route('crm.leads.show', $lead) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">View</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ request()->boolean('demo_requests') ? 9 : 8 }}" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No CRM leads found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $leads->links() }}</div>
        </form>
    </div>
@endsection
