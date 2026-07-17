@extends('layouts.admin')

@section('title', $onboarding->onboarding_number)
@section('page-title', 'Customer Onboarding')

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                <div>
                    <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $onboarding->onboarding_number }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $onboarding->business_name ?: $onboarding->customer?->company_name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $onboarding->customer_contact_name }}{{ $onboarding->customer_contact_phone ? ' · '.$onboarding->customer_contact_phone : '' }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $onboarding->status->label() }}</span>
                    @can('crm.support.create')
                        <a href="{{ route('crm.support.tickets.create', ['onboarding' => $onboarding->id]) }}" class="rounded-lg border border-sky-300 px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-50 dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-950/30">Create Support Ticket</a>
                    @endcan
                    @can('crm.onboarding.update')
                        <a href="{{ route('crm.onboarding.edit', $onboarding) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>
                    @endcan
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Progress</p><p class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">{{ $onboarding->progress_percent }}%</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Priority</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $onboarding->priority->label() }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Implementation owner</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $onboarding->implementationOwner?->name ?? 'Unassigned' }}</p></div>
                <div><p class="text-xs font-semibold uppercase text-slate-500">Target go-live</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $onboarding->target_go_live_date?->format('d M Y') ?? 'Not set' }}</p></div>
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">Linked records</p>
                    <div class="mt-1 flex flex-wrap gap-2 text-sm font-semibold text-teal-700 dark:text-teal-300">
                        @if ($onboarding->customer)<a href="{{ route('crm.customers.show', $onboarding->customer) }}">Customer</a>@endif
                        @if ($onboarding->lead)<a href="{{ route('crm.leads.show', $onboarding->lead) }}">Lead</a>@endif
                        @if ($onboarding->quotation)<a href="{{ route('crm.quotations.show', $onboarding->quotation) }}">Quotation</a>@endif
                        @if ($onboarding->proforma)<a href="{{ route('crm.proformas.show', $onboarding->proforma) }}">Proforma</a>@endif
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
            <div class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-4">
                        <div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Implementation checklist</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Required tasks determine readiness for go-live.</p></div>
                        <div class="h-2 w-28 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full bg-teal-500" style="width: {{ $onboarding->progress_percent }}%"></div></div>
                    </div>

                    <div class="mt-5 space-y-6">
                        @foreach ($onboarding->tasks->groupBy(fn ($task) => $task->category->label()) as $category => $tasks)
                            <div>
                                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $category }}</h3>
                                <div class="mt-3 space-y-2">
                                    @foreach ($tasks as $task)
                                        <div class="flex flex-col gap-3 rounded-lg border border-slate-200 p-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0"><p class="font-medium text-slate-950 dark:text-white">{{ $task->title }} @unless ($task->is_required)<span class="text-xs font-normal text-slate-500">Optional</span>@endunless</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $task->assignee?->name ?? 'Unassigned' }}{{ $task->due_date ? ' · Due '.$task->due_date->format('d M') : '' }}</p></div>
                                            @can('crm.onboarding.complete_task')
                                                <form method="POST" action="{{ route('crm.onboarding.tasks.update', [$onboarding, $task]) }}" class="flex items-center gap-2">@csrf<select name="status" class="text-sm"><option value="{{ $task->status->value }}">{{ $task->status->label() }}</option>@foreach (\App\Enums\Crm\OnboardingTaskStatus::cases() as $status) @if ($status !== $task->status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endif @endforeach</select><button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Update</button></form>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @can('crm.onboarding.update')
                        <details class="mt-5 rounded-lg border border-dashed border-slate-300 p-4 dark:border-slate-700">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">Add a custom task</summary>
                            <form method="POST" action="{{ route('crm.onboarding.tasks.store', $onboarding) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
                                <input name="task_key" required placeholder="task-key" class="w-full">
                                <input name="title" required placeholder="Task title" class="w-full">
                                <select name="category" class="w-full">@foreach (\App\Enums\Crm\OnboardingTaskCategory::cases() as $category)<option value="{{ $category->value }}">{{ $category->label() }}</option>@endforeach</select>
                                <select name="assigned_to" class="w-full"><option value="">Unassigned</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select>
                                <input type="date" name="due_date" class="w-full">
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" checked> Required for go-live</label>
                                <textarea name="description" rows="2" placeholder="Description (optional)" class="sm:col-span-2 w-full"></textarea>
                                <div class="sm:col-span-2"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add task</button></div>
                            </form>
                        </details>
                    @endcan
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Notes</h2>
                    @can('crm.onboarding.update')
                        <form method="POST" action="{{ route('crm.onboarding.notes.store', $onboarding) }}" class="mt-4 space-y-3">@csrf<textarea name="note" required rows="3" placeholder="Add an implementation note" class="w-full"></textarea><div class="flex gap-3"><select name="visibility"><option value="internal">Internal</option><option value="customer_safe">Customer safe</option></select><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add note</button></div></form>
                    @endcan
                    <div class="mt-5 space-y-3">@forelse ($onboarding->onboardingNotes as $note)<article class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"><p class="text-sm text-slate-700 dark:text-slate-200">{{ $note->note }}</p><p class="mt-2 text-xs text-slate-500">{{ $note->visibility === 'customer_safe' ? 'Customer safe' : 'Internal' }} · {{ $note->creator?->name ?? 'System' }} · {{ $note->created_at->diffForHumans() }}</p></article>@empty<p class="text-sm text-slate-500">No onboarding notes yet.</p>@endforelse</div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Activity timeline</h2>
                    <div class="mt-4 space-y-3">@forelse ($onboarding->auditLogs as $audit)<article class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"><p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $audit->description }}</p><p class="mt-1 text-xs text-slate-500">{{ $audit->created_at?->diffForHumans() }} · {{ $audit->user?->name ?? 'System' }}</p></article>@empty<p class="text-sm text-slate-500">No tracked activity yet.</p>@endforelse</div>
                </section>
            </div>

            <aside class="space-y-6">
                @if ($supportSummary)
                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Support</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $supportSummary['open'] }} open ticket{{ $supportSummary['open'] === 1 ? '' : 's' }}.</p></div><a href="{{ route('crm.support.tickets.index', ['search' => $onboarding->onboarding_number]) }}" class="text-xs font-semibold text-sky-700 dark:text-sky-300">View all</a></div>
                        <div class="mt-4 space-y-3">@forelse ($supportSummary['recent'] as $ticket)<a href="{{ route('crm.support.tickets.show', $ticket) }}" class="support-ticket-row block rounded-lg border border-slate-200 p-3 dark:border-slate-800"><p class="text-xs font-semibold text-slate-500">{{ $ticket->ticket_number }}</p><p class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">{{ $ticket->subject }}</p><p class="mt-2 text-xs text-slate-500">{{ $ticket->status->label() }} · {{ $ticket->updated_at->diffForHumans() }}</p></a>@empty<p class="rounded-lg border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-500 dark:border-slate-700">No support tickets are linked to this onboarding.</p>@endforelse</div>
                    </section>
                @endif
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Documents</h2>
                    @can('crm.onboarding.manage_documents')
                        <form method="POST" action="{{ route('crm.onboarding.documents.store', $onboarding) }}" class="mt-4 space-y-3">@csrf<select name="document_type" class="w-full">@foreach (['business_details', 'product_master', 'customer_list', 'supplier_list', 'barcode_list', 'logo', 'gst_certificate', 'training_material', 'other'] as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach</select><input name="title" required placeholder="Document title" class="w-full"><input type="url" name="external_url" placeholder="External file URL (optional)" class="w-full"><select name="status" class="w-full"><option value="requested">Requested</option><option value="received">Received</option><option value="verified">Verified</option><option value="rejected">Rejected</option></select><textarea name="notes" rows="2" placeholder="Notes" class="w-full"></textarea><button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add document</button></form>
                    @endcan
                    <div class="mt-5 space-y-3">@forelse ($onboarding->documents as $document)<div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"><p class="font-medium text-slate-950 dark:text-white">{{ $document->title }}</p><p class="mt-1 text-xs text-slate-500">{{ str($document->document_type)->headline() }} · {{ $document->status->label() }}</p>@if ($document->external_url)<a href="{{ $document->external_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block text-xs font-semibold text-teal-700 dark:text-teal-300">Open external file</a>@endif @can('crm.onboarding.manage_documents')<form method="POST" action="{{ route('crm.onboarding.documents.update', [$onboarding, $document]) }}" class="mt-3 flex gap-2">@csrf @method('PUT')<input type="hidden" name="document_type" value="{{ $document->document_type }}"><input type="hidden" name="title" value="{{ $document->title }}"><input type="hidden" name="external_url" value="{{ $document->external_url }}"><input type="hidden" name="notes" value="{{ $document->notes }}"><select name="status" class="min-w-0 flex-1 text-xs">@foreach (\App\Enums\Crm\OnboardingDocumentStatus::cases() as $status)<option value="{{ $status->value }}" @selected($status === $document->status)>{{ $status->label() }}</option>@endforeach</select><button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Save</button></form>@endcan</div>@empty<p class="text-sm text-slate-500">No document requests yet.</p>@endforelse</div>
                </section>

                @can('crm.onboarding.update')
                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">Lifecycle</h2><div class="mt-4 grid gap-2">@foreach ([['go_live_ready', 'Mark go-live ready'], ['live', 'Mark live'], ['on_hold', 'Put on hold']] as [$status, $label])<form method="POST" action="{{ route('crm.onboarding.status', $onboarding) }}">@csrf<input type="hidden" name="status" value="{{ $status }}"><button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ $label }}</button></form>@endforeach @can('crm.onboarding.cancel')<form method="POST" action="{{ route('crm.onboarding.status', $onboarding) }}">@csrf<input type="hidden" name="status" value="cancelled"><button class="w-full rounded-lg border border-rose-300 px-4 py-2 text-left text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/20">Cancel onboarding</button></form>@endcan</div></section>
                @endcan
            </aside>
        </section>
    </div>
@endsection
