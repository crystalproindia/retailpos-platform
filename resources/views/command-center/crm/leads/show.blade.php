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
                    @can('crm.demos.create')
                        <a href="{{ route('crm.demos.create', $lead) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Schedule Demo</a>
                    @endcan
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
                        'Lead Name' => $lead->contact_name,
                        'Email' => $lead->email,
                        'Phone' => $lead->phone,
                        'Location' => collect([$lead->city, $lead->country])->filter()->join(', '),
                        'Business Type' => $lead->business_type ?? $lead->industry,
                        'Estimated Budget' => $lead->expected_value !== null ? '₹'.number_format((float) $lead->expected_value, 0) : null,
                        'Expected Timeline' => $lead->expected_timeline,
                        'Follow-up Date' => $lead->next_follow_up_at?->format('d M Y, h:i A'),
                        'Last Contacted' => $lead->last_contacted_at?->format('d M Y, h:i A'),
                        'Converted' => $lead->converted_at?->format('d M Y, h:i A') ?? 'No',
                        'Won At' => $lead->won_at?->format('d M Y, h:i A'),
                        'Lost At' => $lead->lost_at?->format('d M Y, h:i A'),
                        'Lost Reason' => $lead->lost_reason,
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value ?? 'N/A' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Requirement</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $lead->description ?: 'No requirement recorded yet.' }}</p>
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Activity & Notes</h2>
                <form method="POST" action="{{ route('crm.leads.notes.store', $lead) }}" class="mt-5">
                    @csrf
                    <textarea name="body" rows="3" required placeholder="Add a note" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                    <button class="mt-3 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Add note</button>
                </form>
                <div class="mt-6 space-y-3">
                    @forelse ($lead->auditLogs as $auditLog)
                        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $auditLog->description }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $auditLog->created_at?->format('d M Y, h:i A') }} by {{ $auditLog->user?->name ?? 'System' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No activity has been recorded yet.</p>
                    @endforelse
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

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Demo Schedule History</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Track internal schedules and their Google Calendar sync state.</p>
                </div>
                @can('crm.demos.create')
                    <a href="{{ route('crm.demos.create', $lead) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Schedule demo</a>
                @endcan
            </div>
            <div class="mt-5 space-y-3">
                @forelse ($lead->demoSchedules as $demo)
                    @php
                        $statusClass = match ($demo->status?->tone()) {
                            'success' => 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-100',
                            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
                            'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-100',
                            default => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-100',
                        };
                        $calendarSyncClass = match ($demo->calendar_sync_status) {
                            'synced' => 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-100',
                            'failed' => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-100',
                            'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
                            'cancelled' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
                            default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
                        };
                        $calendarSyncLabel = match ($demo->calendar_sync_status) {
                            'synced' => 'Calendar synced',
                            'failed' => 'Calendar sync failed',
                            'pending' => 'Calendar sync pending',
                            'cancelled' => 'Calendar event cancelled',
                            default => 'Not synced',
                        };
                    @endphp
                    <article class="flex flex-col gap-4 rounded-lg border border-slate-200 p-4 md:flex-row md:items-start md:justify-between dark:border-slate-800">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-950 dark:text-white">{{ $demo->starts_at?->setTimezone($demo->timezone)->format('d M Y, h:i A') }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $demo->status?->label() }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $calendarSyncClass }}">{{ $calendarSyncLabel }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $demo->meeting_mode?->label() }} · {{ $demo->assignedTo?->name ?? 'Unassigned' }}</p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                @if ($demo->meeting_link)<a href="{{ $demo->meeting_link }}" target="_blank" rel="noreferrer" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open meeting link</a>@endif
                                @if ($demo->external_meeting_link)<button type="button" data-copy-text="{{ $demo->external_meeting_link }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Copy meeting link</button>@endif
                                @if ($demo->external_calendar_event_url)<a href="{{ $demo->external_calendar_event_url }}" target="_blank" rel="noreferrer" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Open Google Calendar event</a>@endif
                            </div>
                            @if ($demo->notes)<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $demo->notes }}</p>@endif
                        </div>
                        @if ($demo->isActive())
                            <div class="flex flex-wrap gap-2">
                                @can('crm.demos.sync_calendar')
                                @if ($googleCalendarConnected)
                                    <form method="POST" action="{{ route('crm.demos.sync-google-calendar', $demo) }}" class="flex items-center gap-2">
                                        @csrf
                                        @if ($demo->meeting_mode?->value === 'google_meet_later')
                                            <label class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"><input type="checkbox" name="create_google_meet" value="1" class="rounded border-slate-300"> Meet</label>
                                        @endif
                                        <button class="rounded-lg border border-sky-200 px-3 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50 dark:border-sky-900 dark:text-sky-300 dark:hover:bg-sky-950">{{ $demo->external_calendar_event_id ? 'Re-sync calendar' : 'Sync to Google Calendar' }}</button>
                                    </form>
                                @else
                                    <span class="self-center text-xs font-medium text-slate-500 dark:text-slate-400">Google Calendar not connected</span>
                                @endif
                                @endcan
                                @can('crm.demos.update')<a href="{{ route('crm.demos.edit', $demo) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Reschedule</a>@endcan
                                @can('crm.demos.complete')<form method="POST" action="{{ route('crm.demos.complete', $demo) }}">@csrf<button class="rounded-lg border border-teal-200 px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50 dark:border-teal-900 dark:text-teal-300 dark:hover:bg-teal-950">Complete</button></form>@endcan
                                @can('crm.demos.cancel')<form method="POST" action="{{ route('crm.demos.cancel', $demo) }}">@csrf<button class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950">Cancel</button></form>@endcan
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No demos have been scheduled for this lead.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
