@extends('layouts.admin')

@section('title', 'Task')
@section('page-title', 'Task')
@section('breadcrumbs')
    <a href="{{ route('tasks.index') }}">Tasks</a><span>/</span><span>Task</span>
@endsection

@section('content')
    @php
        $personal = $task->task_type->value === 'personal';
        $isLeadTask = ! $personal && $task->related instanceof \App\Models\Crm\CrmLead;
        $priorityClasses = [
            'low' => 'bg-slate-100 text-slate-600',
            'normal' => 'bg-sky-50 text-sky-700',
            'high' => 'bg-amber-50 text-amber-800',
            'urgent' => 'bg-rose-50 text-rose-800',
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        @if ($errors->any())
            <section class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-100" role="alert" aria-labelledby="task-validation-title">
                <h2 id="task-validation-title" class="font-semibold">Please review the task details</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('tasks.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800 dark:text-teal-300">Back to tasks</a>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $personal ? 'bg-violet-100 text-violet-800' : 'bg-teal-100 text-teal-800' }}">{{ $task->task_type->label() }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $priorityClasses[$task->priority->value] }}">{{ $task->priority->label() }}</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $task->status->label() }}</span>
                </div>
                <h1 class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white">{{ $task->title }}</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{ $task->due_at ? 'Due '.$task->due_at->format('l, j F Y g:i A') : 'No due date has been set.' }}
                </p>
            </div>

            @if ($canManage && $task->status->isOpen())
                <form method="POST" action="{{ route('tasks.transition', $task) }}">
                    @csrf
                    <input type="hidden" name="status" value="completed">
                    <button class="min-h-11 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Complete task</button>
                </form>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.8fr]">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold text-slate-950 dark:text-white">Details</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $task->description ?: 'No extra notes have been added.' }}</p>

                @if (! $personal && $relatedLabel)
                    <div class="mt-5 rounded-lg bg-slate-50 p-4 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked record</p>
                        @if ($relatedUrl)
                            <a href="{{ $relatedUrl }}" class="mt-1 inline-block font-semibold text-teal-700 hover:text-teal-800 dark:text-teal-300">{{ $relatedLabel }}</a>
                        @else
                            <p class="mt-1 font-medium text-slate-700 dark:text-slate-200">Record unavailable</p>
                        @endif
                    </div>
                @endif

                @if ($task->completion_note)
                    <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Completion note</p>
                        <p class="mt-1 text-sm text-emerald-900 dark:text-emerald-100">{{ $task->completion_note }}</p>
                    </div>
                @endif
            </section>

            <aside class="space-y-4">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold text-slate-950 dark:text-white">Task context</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Assigned to</dt>
                            <dd class="font-medium text-slate-900 dark:text-white">{{ $task->assignee?->name ?: 'Unassigned' }}</dd>
                        </div>
                        @if (! $personal)
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Outlet</dt>
                                <dd class="font-medium text-slate-900 dark:text-white">{{ $task->outlet?->name ?: 'Company wide' }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Created</dt>
                            <dd class="font-medium text-slate-900 dark:text-white">{{ $task->created_at->format('j M Y') }}</dd>
                        </div>
                        @if ($task->completed_at)
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Completed</dt>
                                <dd class="font-medium text-slate-900 dark:text-white">{{ $task->completed_at->format('j M Y g:i A') }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>

                @if ($canManage)
                    <section id="update-task" class="scroll-mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="font-semibold text-slate-950 dark:text-white">Update task</h2>
                        <form method="POST" action="{{ route('tasks.update', $task) }}" class="mt-4 space-y-3">
                            @csrf
                            @method('PUT')
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Task title</span>
                                <input name="title" value="{{ old('title', $task->title) }}" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Priority</span>
                                <select name="priority" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority->value }}" @selected(old('priority', $task->priority->value) === $priority->value)>{{ $priority->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Due date and time</span>
                                <input name="due_at" type="datetime-local" value="{{ old('due_at', $task->due_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Reminder</span>
                                <input name="reminder_at" type="datetime-local" value="{{ old('reminder_at', $task->reminder_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                            </label>
                            @if (! $personal && $canReassign)
                                <label class="block">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Assigned to</span>
                                    <select name="assigned_user_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" @selected((int) old('assigned_user_id', $task->assigned_user_id) === $user->id)>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Outlet</span>
                                    <select name="outlet_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                                        <option value="">Company wide</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" @selected((int) old('outlet_id', $task->outlet_id) === $outlet->id)>{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            @can('tasks.manage_recurring')
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Repeat</span>
                                        <select name="recurrence_type" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                                            <option value="">Does not repeat</option>
                                            @foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'interval' => 'Every number of days'] as $value => $label)
                                                <option value="{{ $value }}" @selected(old('recurrence_type', $task->recurrence_type?->value) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Repeat interval</span>
                                        <input name="recurrence_interval" type="number" min="1" max="365" value="{{ old('recurrence_interval', $task->recurrence_interval) }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Only for custom repeat">
                                    </label>
                                </div>
                                @if ($task->recurrence_series_id)
                                    <label class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100"><input name="cancel_series" type="checkbox" value="1" class="mt-1"> <span>Stop future repeats. Completed history will remain intact.</span></label>
                                @endif
                            @endcan
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Notes</span>
                                <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description', $task->description) }}</textarea>
                            </label>
                            <button class="min-h-11 w-full rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Save changes</button>
                        </form>
                    </section>

                    <section class="rounded-xl border border-rose-200 bg-rose-50 p-5 shadow-sm dark:border-rose-900 dark:bg-rose-950/20">
                        <h2 class="font-semibold text-rose-950 dark:text-rose-100">Archive task</h2>
                        <p class="mt-1 text-sm text-rose-800 dark:text-rose-200">Archived tasks leave the active lists but remain preserved for audit history.</p>
                        @can('tasks.archive')
                            <button type="button" class="mt-4 min-h-11 rounded-lg border border-rose-300 px-4 text-sm font-semibold text-rose-800 hover:bg-rose-100 dark:border-rose-800 dark:text-rose-200" data-confirm-trigger data-confirm-action="{{ route('tasks.archive', $task) }}" data-confirm-method="POST" data-confirm-title="Archive this task?" data-confirm-message="It will be removed from active task lists while the audit history remains preserved.">Archive task</button>
                        @endcan
                    </section>
                @endif
            </aside>
        </div>

        @if ($canManage && $task->status->isOpen())
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold text-slate-950 dark:text-white">Complete with context</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Completion never changes a lead, invoice, or ticket automatically.</p>
                <form method="POST" action="{{ route('tasks.transition', $task) }}" class="mt-4 grid gap-3 lg:grid-cols-[1fr_1fr_auto]">
                    @csrf
                    <input type="hidden" name="status" value="completed">
                    <textarea name="completion_note" rows="2" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Optional completion note"></textarea>
                    @if ($isLeadTask)
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block"><span class="sr-only">Next follow-up title</span><input name="next_follow_up_title" class="min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Next follow-up title"></label>
                            <label class="block"><span class="sr-only">Next follow-up date and time</span><input name="next_follow_up_at" type="datetime-local" class="min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                        </div>
                    @else
                        <div></div>
                    @endif
                    <button class="min-h-11 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">Complete</button>
                </form>
            </section>
        @endif

        @if ($canManage)
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold text-slate-950 dark:text-white">Change status</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Status changes stay within the task. They never automatically alter a linked CRM record.</p>
                <form method="POST" action="{{ route('tasks.transition', $task) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <label class="block flex-1"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">Status</span><select name="status" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                        @foreach ($statuses as $status)
                            @php($isReopen = $status->value === 'todo' && in_array($task->status->value, ['completed', 'cancelled'], true))
                            @if ($status === $task->status || ($task->status->canTransitionTo($status) && (! $isReopen || $canReopen)))
                                <option value="{{ $status->value }}" @selected($task->status === $status)>{{ $status->label() }}</option>
                            @endif
                        @endforeach
                    </select></label>
                    <button class="min-h-11 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Update status</button>
                </form>
            </section>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold text-slate-950 dark:text-white">Task history</h2>
            <div class="mt-4 space-y-3">
                @forelse ($task->auditLogs->take(12) as $audit)
                    <article class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $audit->description }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $audit->created_at?->format('d M Y, g:i A') }} by {{ $audit->user?->name ?? 'System' }}</p>
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No tracked task activity yet.</p>
                @endforelse
            </div>
        </section>
    </div>
    @include('command-center.crm.settings.partials.confirm-dialog')
@endsection
