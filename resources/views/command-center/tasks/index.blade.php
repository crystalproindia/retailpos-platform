@extends('layouts.admin')

@section('title', 'Tasks')
@section('page-title', 'Tasks')
@section('breadcrumbs')
    <span>/</span><span>Tasks</span>
@endsection

@section('content')
    @php
        $tabs = [
            'all' => ['label' => 'My tasks', 'route' => 'tasks.index'],
            'today' => ['label' => 'Today', 'route' => 'tasks.today'],
            'upcoming' => ['label' => 'Upcoming', 'route' => 'tasks.upcoming'],
            'overdue' => ['label' => 'Overdue', 'route' => 'tasks.overdue'],
            'completed' => ['label' => 'Completed', 'route' => 'tasks.completed'],
            'personal' => ['label' => 'Personal', 'route' => 'tasks.personal'],
            'work' => ['label' => 'Work', 'route' => 'tasks.work'],
        ];
        if (auth()->user()->can('tasks.view_team')) $tabs['team'] = ['label' => 'Team', 'route' => 'tasks.team'];
        $statusClasses = ['todo' => 'bg-slate-100 text-slate-700', 'in_progress' => 'bg-sky-100 text-sky-800', 'waiting' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-slate-200 text-slate-600'];
        $priorityClasses = ['low' => 'bg-slate-100 text-slate-600', 'normal' => 'bg-sky-50 text-sky-700', 'high' => 'bg-amber-50 text-amber-800', 'urgent' => 'bg-rose-50 text-rose-800'];
    @endphp
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">Smart Tasks</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">A calmer way to keep work moving</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Personal tasks stay private. Work tasks stay linked to the customers, leads, and outlets that need attention.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('tasks.export')
                    <a href="{{ route('tasks.export', request()->query()) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Export work CSV</a>
                @endcan
                @can('tasks.create')
                    <a href="#quick-add" class="hidden min-h-11 items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 lg:inline-flex dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100">
                        <span class="text-lg leading-none" aria-hidden="true">+</span> Add task
                    </a>
                @endcan
            </div>
        </div>

        <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Task views">
            @foreach($tabs as $key => $tab)
                <a href="{{ route($tab['route']) }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold transition {{ $view === $key ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">{{ $tab['label'] }}</a>
            @endforeach
            <a href="{{ route('tasks.calendar') }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Calendar</a>
        </nav>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['label' => 'Due today', 'value' => $workMetrics['today'], 'route' => 'tasks.today', 'tone' => 'border-sky-200 bg-sky-50 dark:border-sky-900 dark:bg-sky-950/30'],
                ['label' => 'Overdue work', 'value' => $workMetrics['overdue'], 'route' => 'tasks.overdue', 'tone' => 'border-rose-200 bg-rose-50 dark:border-rose-900 dark:bg-rose-950/30'],
                ['label' => 'Upcoming work', 'value' => $workMetrics['upcoming'], 'route' => 'tasks.upcoming', 'tone' => 'border-teal-200 bg-teal-50 dark:border-teal-900 dark:bg-teal-950/30'],
                ['label' => 'Personal tasks', 'value' => $personalMetrics['today'] + $personalMetrics['upcoming'] + $personalMetrics['overdue'], 'route' => 'tasks.personal', 'tone' => 'border-violet-200 bg-violet-50 dark:border-violet-900 dark:bg-violet-950/30'],
                ['label' => 'Completed today', 'value' => $workMetrics['completed_today'], 'route' => 'tasks.completed', 'tone' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30'],
                ['label' => 'Lead follow-ups', 'value' => $workMetrics['lead_follow_ups'], 'route' => 'tasks.work', 'tone' => 'border-indigo-200 bg-indigo-50 dark:border-indigo-900 dark:bg-indigo-950/30'],
                ['label' => 'Payment follow-ups', 'value' => $workMetrics['payment_follow_ups'], 'route' => 'tasks.work', 'tone' => 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30'],
            ] as $metric)
                <a href="{{ route($metric['route']) }}" class="rounded-xl border p-4 shadow-sm transition duration-150 hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none {{ $metric['tone'] }}">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $metric['value'] }}</p>
                </a>
            @endforeach
        </section>

        @if ($teamMetrics)
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950 dark:text-white">Authorized team workload</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Only work tasks in your permitted outlets are included. Personal tasks are never counted here.</p></div><a href="{{ route('tasks.team') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800 dark:text-teal-300">Open team tasks</a></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-4"><div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950"><p class="text-xs text-slate-500">Due today</p><p class="mt-1 text-xl font-semibold">{{ $teamMetrics['due_today'] }}</p></div><div class="rounded-lg bg-rose-50 p-3 dark:bg-rose-950/30"><p class="text-xs text-rose-700 dark:text-rose-300">Overdue</p><p class="mt-1 text-xl font-semibold">{{ $teamMetrics['overdue'] }}</p></div><div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30"><p class="text-xs text-amber-700 dark:text-amber-300">Unassigned</p><p class="mt-1 text-xl font-semibold">{{ $teamMetrics['unassigned'] }}</p></div><div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-950/30"><p class="text-xs text-emerald-700 dark:text-emerald-300">Completed today</p><p class="mt-1 text-xl font-semibold">{{ $teamMetrics['completed_today'] }}</p></div></div>
            </section>
        @endif

        @can('tasks.create')
            <section id="quick-add" class="scroll-mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Quick add</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create a task in a few seconds. Create linked work tasks from the relevant customer, lead, invoice, or support screen.</p>
                    @if ($prefill)
                        <p class="mt-3 inline-flex rounded-lg bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 dark:bg-teal-950/40 dark:text-teal-200">Linked to {{ $prefill['label'] }}. This will be created as a work task.</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('tasks.store') }}" class="mt-5 grid gap-4 lg:grid-cols-6">@csrf
                    @if ($prefill)
                        <input type="hidden" name="related_type" value="{{ $prefill['type'] }}">
                        <input type="hidden" name="related_id" value="{{ $prefill['id'] }}">
                    @endif
                    <label class="lg:col-span-2"><span class="text-sm font-semibold text-slate-700 dark:text-slate-200">What needs doing?</span><input required name="title" value="{{ old('title') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-950" placeholder="e.g. Call Riya about the store rollout"></label>
                    <fieldset><legend class="text-sm font-semibold text-slate-700 dark:text-slate-200">Type</legend><div class="mt-2 flex min-h-11 items-center gap-3">
                        @if (! $prefill)
                            <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="task_type" value="personal" @checked(old('task_type') === 'personal')> Personal</label>
                        @endif
                        <label class="inline-flex items-center gap-2 text-sm"><input type="radio" name="task_type" value="work" @checked($prefill || old('task_type', 'work') === 'work')> Work</label>
                    </div></fieldset>
                    <label><span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Due</span><input name="due_at" type="datetime-local" value="{{ old('due_at') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                    <label><span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Priority</span><select name="priority" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(old('priority', 'normal') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select></label>
                    <div class="flex items-end"><button class="min-h-11 w-full rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950">Create task</button></div>
                    <div class="lg:col-span-6 grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-4 dark:border-slate-800">
                        <label><span class="text-xs font-semibold text-slate-500">Assign work to</span><select name="assigned_user_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@can('tasks.assign')<option value="">Unassigned work</option>@endcan<option value="{{ auth()->id() }}" @selected((string) old('assigned_user_id', auth()->id()) === (string) auth()->id())>Me</option>@foreach($users->where('id', '!=', auth()->id()) as $user)<option value="{{ $user->id }}" @selected((string) old('assigned_user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></label>
                        <label><span class="text-xs font-semibold text-slate-500">Outlet for work</span><select name="outlet_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">No outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $prefill['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>@endforeach</select></label>
                        <label><span class="text-xs font-semibold text-slate-500">Reminder</span><input name="reminder_at" type="datetime-local" value="{{ old('reminder_at') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="mt-1 block text-xs text-slate-500">Personal reminders never leave your account.</span></label>
                        @can('tasks.manage_recurring')
                            <label><span class="text-xs font-semibold text-slate-500">Repeat</span><select name="recurrence_type" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Does not repeat</option><option value="daily" @selected(old('recurrence_type') === 'daily')>Daily</option><option value="weekly" @selected(old('recurrence_type') === 'weekly')>Weekly</option><option value="monthly" @selected(old('recurrence_type') === 'monthly')>Monthly</option><option value="interval" @selected(old('recurrence_type') === 'interval')>Every number of days</option></select></label>
                            <label><span class="text-xs font-semibold text-slate-500">Repeat interval</span><input name="recurrence_interval" type="number" min="1" max="365" value="{{ old('recurrence_interval') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Only for custom repeat"></label>
                        @endcan
                    </div>
                </form>
            </section>
        @endcan

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

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-800 lg:grid-cols-6">
                <input type="hidden" name="view" value="{{ $view !== 'all' ? $view : '' }}">
                <label class="lg:col-span-2"><span class="sr-only">Search tasks</span><input name="search" value="{{ $filters['search'] ?? '' }}" class="min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-950" placeholder="Search your tasks"></label>
                <select name="status" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                <select name="priority" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All priorities</option>@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select>
                <select name="outlet_id" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All permitted outlets</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((string)($filters['outlet_id'] ?? '') === (string)$outlet->id)>{{ $outlet->name }}</option>@endforeach</select>
                <select name="task_type" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Personal and work</option>@foreach($types as $type)<option value="{{ $type->value }}" @selected(($filters['task_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
                <select name="related_type" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All linked records</option>@foreach($relatedTypes as $key => $label)<option value="{{ $key }}" @selected(($filters['related_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
                <select name="assigned_user_id" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All assignees</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)($filters['assigned_user_id'] ?? '') === (string)$user->id)>{{ $user->name }}</option>@endforeach</select>
                <select name="created_by_user_id" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All creators</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)($filters['created_by_user_id'] ?? '') === (string)$user->id)>{{ $user->name }}</option>@endforeach</select>
                <select name="source_type" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All sources</option>@foreach($sourceTypes as $sourceType)<option value="{{ $sourceType->value }}" @selected(($filters['source_type'] ?? '') === $sourceType->value)>{{ $sourceType->label() }}</option>@endforeach</select>
                <input name="due_from" type="date" value="{{ $filters['due_from'] ?? '' }}" aria-label="Due from" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <input name="due_to" type="date" value="{{ $filters['due_to'] ?? '' }}" aria-label="Due to" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <input name="completed_from" type="date" value="{{ $filters['completed_from'] ?? '' }}" aria-label="Completed from" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <input name="completed_to" type="date" value="{{ $filters['completed_to'] ?? '' }}" aria-label="Completed to" class="min-h-11 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <div class="flex gap-2"><button class="min-h-11 flex-1 rounded-lg bg-slate-950 px-3 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950">Apply</button><a href="{{ route($tabs[$view]['route'] ?? 'tasks.index') }}" class="grid min-h-11 place-items-center rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Reset</a></div>
            </form>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($tasks as $task)
                    @php $isPersonal = $task->task_type->value === 'personal'; @endphp
                    <article class="group flex flex-col gap-3 px-4 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-950 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2"><a href="{{ route('tasks.show', $task) }}" class="truncate font-semibold text-slate-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">{{ $task->title }}</a><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $isPersonal ? 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200' : 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200' }}">{{ $task->task_type->label() }}</span><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $priorityClasses[$task->priority->value] }}">{{ $task->priority->label() }}</span><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClasses[$task->status->value] }}">{{ $task->status->label() }}</span></div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                                <span class="{{ $task->isOverdue() ? 'font-semibold text-rose-700 dark:text-rose-300' : '' }}">{{ $task->due_at ? ($task->isOverdue() ? 'Overdue: ' : 'Due: ').$task->due_at->format('D, j M g:i A') : 'No due date' }}</span>
                                @if (! $isPersonal && $task->assignee)
                                    <span class="inline-flex items-center gap-1.5"><span class="grid size-5 place-items-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-100" aria-hidden="true">{{ str($task->assignee->name)->substr(0, 1)->upper() }}</span> Assigned to {{ $task->assignee->name }}</span>
                                @endif
                                @if (! $isPersonal && $task->outlet)
                                    <span>{{ $task->outlet->name }}</span>
                                @endif
                                @if (! $isPersonal && $task->source_type->value === 'system_rule')
                                    <span>Rule generated</span>
                                @endif
                                @if (! $isPersonal && $task->related && $relatedRegistry->isVisibleTo(auth()->user(), $task->related))
                                    <a href="{{ $relatedRegistry->routeFor($task->related) }}" class="font-medium text-teal-700 hover:text-teal-800 dark:text-teal-300">{{ $relatedRegistry->label($task->related) }}</a>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if($task->status->isOpen() && in_array($task->id, $manageableTaskIds, true))
                                <form method="POST" action="{{ route('tasks.transition', $task) }}">@csrf<input type="hidden" name="status" value="completed"><button class="min-h-11 rounded-lg border border-emerald-200 px-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 dark:border-emerald-900 dark:text-emerald-300">Complete</button></form>
                            @endif
                            @if($task->status->isOpen() && in_array($task->id, $manageableTaskIds, true))
                                <a href="{{ route('tasks.show', $task) }}#update-task" class="grid min-h-11 place-items-center rounded-lg border border-sky-200 px-3 text-sm font-semibold text-sky-700 hover:bg-sky-50 dark:border-sky-900 dark:text-sky-300">Reschedule</a>
                            @endif
                            <a href="{{ route('tasks.show', $task) }}" class="grid min-h-11 min-w-11 place-items-center rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200">Open</a>
                        </div>
                    </article>
                @empty
                    <div class="p-10 text-center"><p class="font-semibold text-slate-900 dark:text-white">Nothing needs your attention here.</p><p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Add a task for yourself, or create a work task from a customer or lead when there is a next action.</p></div>
                @endforelse
            </div>
            @if($tasks->hasPages())<div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $tasks->links() }}</div>@endif
        </section>

        @can('tasks.create')
            <a href="#quick-add" class="fixed bottom-5 right-4 z-30 inline-flex min-h-12 items-center gap-2 rounded-full bg-slate-950 px-4 text-sm font-semibold text-white shadow-lg transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 sm:right-6 lg:hidden dark:bg-white dark:text-slate-950" aria-label="Add a task">
                <span class="text-lg leading-none" aria-hidden="true">+</span> Add task
            </a>
        @endcan
    </div>
@endsection
