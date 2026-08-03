@extends('layouts.admin')

@section('title', $lead->title)
@section('page-title', $lead->title)

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Leads</span><span>/</span><span>{{ $lead->id }}</span>
@endsection

@section('content')
    @php
        $conversationAssessment = $lead->conversationAssessment();
        $assessmentAudit = $lead->auditLogs->firstWhere('event', 'crm.lead.conversation_assessment_updated');
    @endphp
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
                    @can('crm.quotations.create')
                        <a href="{{ route('crm.quotations.create', $lead) }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create quotation</a>
                    @endcan
                    @can('sales.opportunities.create')
                        <a href="{{ route('sales.opportunities.create', $lead) }}" class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 dark:border-teal-800 dark:text-teal-200">Create opportunity</a>
                    @endcan
                    @can('crm.demos.create')
                        <a href="{{ route('crm.demos.create', $lead) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Schedule Demo</a>
                    @endcan
                    @can('tasks.create_work')
                        <a href="#lead-task" class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 dark:border-teal-800 dark:text-teal-200">Add task</a>
                    @endcan
                    @can('crm.leads.update')
                        <a href="{{ route('crm.leads.edit', $lead) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit lead</a>
                        <a href="{{ route('crm.leads.edit', $lead) }}#conversation-assessment" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit assessment</a>
                    @endcan
                    @if ($lead->crmCustomer)
                        <a href="{{ route('crm.customers.show', $lead->crmCustomer) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">View customer</a>
                    @else
                        @can('crm.customers.convert')
                            <a href="{{ route('crm.customers.create-for-lead', $lead) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Convert to customer</a>
                        @endcan
                    @endif
                </div>
            </div>
        </section>

        @can('crm.ai.view')
            @include('command-center.crm.leads.partials.ai-assistant')
        @endcan

        @can('tasks.view')
            <section id="lead-task" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Lead tasks</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The next actions linked to this lead. Completing a task records a CRM history note without changing lead status.</p></div><a href="{{ route('tasks.work') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open task workspace</a></div>
                @can('tasks.create_work')
                    <form method="POST" action="{{ route('tasks.store') }}" class="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-4 dark:bg-slate-950">@csrf<input type="hidden" name="task_type" value="work"><input type="hidden" name="related_type" value="lead"><input type="hidden" name="related_id" value="{{ $lead->id }}"><input type="hidden" name="outlet_id" value="{{ $lead->branch_id }}"><input type="hidden" name="assigned_user_id" value="{{ $lead->assigned_user_id ?? auth()->id() }}"><label class="sm:col-span-2"><span class="sr-only">Task title</span><input name="title" required class="min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Next action for this lead"></label><input name="due_at" type="datetime-local" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"><div class="flex gap-2"><select name="priority" class="min-h-11 min-w-0 flex-1 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select><button class="min-h-11 rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create</button></div></form>
                @endcan
                <div class="mt-4 space-y-2">@forelse($openTasks as $task)<a href="{{ route('tasks.show', $task) }}" class="flex flex-col justify-between gap-2 rounded-lg border border-slate-200 p-3 transition hover:bg-slate-50 sm:flex-row sm:items-center dark:border-slate-800 dark:hover:bg-slate-950"><span class="font-medium text-slate-950 dark:text-white">{{ $task->title }}</span><span class="text-sm {{ $task->isOverdue() ? 'font-semibold text-rose-700 dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $task->due_at ? ($task->isOverdue() ? 'Overdue ' : 'Due ').$task->due_at->format('d M, g:i A') : 'No due date' }}</span></a>@empty<p class="rounded-lg border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No open work tasks are linked to this lead.</p>@endforelse</div>
            </section>
        @endcan

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
                <div id="conversation-assessment" class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Conversation Assessment</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Staff-entered conversation assessment, not an AI prediction.</p>
                        </div>
                        @include('command-center.crm.leads.partials.conversation-assessment-badge', ['assessment' => $conversationAssessment])
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="text-slate-500 dark:text-slate-400">Client receptiveness</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $lead->client_receptiveness_rating ? $lead->client_receptiveness_rating.' / 5' : 'Not rated' }}</dd></div>
                        <div><dt class="text-slate-500 dark:text-slate-400">Buying interest</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $lead->buying_interest_rating ? $lead->buying_interest_rating.' / 5' : 'Not rated' }}</dd></div>
                        <div><dt class="text-slate-500 dark:text-slate-400">Follow-up urgency</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $lead->follow_up_urgency_rating ? $lead->follow_up_urgency_rating.' / 5' : 'Not rated' }}</dd></div>
                    </dl>
                    <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">Conversation score: <span class="font-semibold text-slate-900 dark:text-white">{{ $conversationAssessment->average !== null ? number_format($conversationAssessment->average, 1).' / 5' : 'Not Rated' }}</span>@if ($assessmentAudit) <span class="text-slate-500 dark:text-slate-400">· Updated {{ $assessmentAudit->created_at?->format('d M Y, h:i A') }} by {{ $assessmentAudit->user?->name ?? 'System' }}</span>@endif</p>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Requirement</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $lead->description ?: 'No requirement recorded yet.' }}</p>
                </div>
                @if ($lead->crmCustomer)
                    <div class="mt-5 rounded-lg border border-teal-200 bg-teal-50 p-4 dark:border-teal-900/70 dark:bg-teal-950/30">
                        <p class="text-xs font-semibold uppercase text-teal-800 dark:text-teal-200">Converted customer</p>
                        <a href="{{ route('crm.customers.show', $lead->crmCustomer) }}" class="mt-1 block text-sm font-semibold text-slate-950 hover:underline dark:text-white">{{ $lead->crmCustomer->customer_code }} · {{ $lead->crmCustomer->display_name }}</a>
                    </div>
                @endif
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
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Track internal schedules and their progress.</p>
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
                    @endphp
                    <article class="flex flex-col gap-4 rounded-lg border border-slate-200 p-4 md:flex-row md:items-start md:justify-between dark:border-slate-800">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-slate-950 dark:text-white">{{ $demo->starts_at?->setTimezone($demo->timezone)->format('d M Y, h:i A') }}</p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass }}">{{ $demo->status?->label() }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $demo->meeting_mode?->label() }} · {{ $demo->assignedTo?->name ?? 'Unassigned' }}</p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                @if ($demo->meeting_link)<a href="{{ $demo->meeting_link }}" target="_blank" rel="noreferrer" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open meeting link</a>@endif
                            </div>
                            @if ($demo->notes)<p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $demo->notes }}</p>@endif
                        </div>
                        @if ($demo->isActive())
                            <div class="flex flex-wrap gap-2">
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

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-4"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Related Quotations</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Proposal history linked to this lead.</p></div>@can('crm.quotations.create')<a href="{{ route('crm.quotations.create', $lead) }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Create quotation</a>@endcan</div>
            <div class="mt-5 space-y-3">@forelse($lead->quotations as $quotation)<a href="{{ route('crm.quotations.show', $quotation) }}" class="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50 sm:flex-row sm:items-center dark:border-slate-800 dark:hover:bg-slate-800"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $quotation->quotation_number }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $quotation->title }} · {{ $quotation->created_at?->format('d M Y') }}</p></div><div class="flex items-center gap-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $quotation->status?->label() }}</span><strong class="text-sm text-slate-950 dark:text-white">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</strong></div></a>@empty<p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No quotations have been created for this lead.</p>@endforelse</div>
        </section>
    </div>
@endsection
