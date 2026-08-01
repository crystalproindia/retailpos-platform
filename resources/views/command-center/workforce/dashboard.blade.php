@extends('layouts.admin')

@section('title', 'Workforce')
@section('page-title', 'Workforce')
@section('breadcrumbs')
    <span>/</span><span>Workforce</span>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-teal-700">Workforce Dashboard</p>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">People, access, and operational context</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-500">Operational metrics appear only when a reliable source record exists. They are not an overall performance score.</p>
            </div>
            @can('workforce.manage')
                <a class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" href="{{ route('workforce.employees.create') }}">Add employee</a>
            @endcan
            @can('tasks.create_work')
                <a class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800 transition hover:bg-teal-50" href="{{ route('tasks.index') }}#quick-add">Add work task</a>
            @endcan
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach(['Total employees' => 'total', 'Active employees' => 'active', 'Inactive or suspended' => 'inactive', 'Without login access' => 'without_login', 'Pending invitations' => 'pending_invitations'] as $label => $key)
                <a href="{{ route('workforce.employees.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-md">
                    <p class="text-sm text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">{{ $metrics[$key] }}</p>
                </a>
            @endforeach
        </div>

        @if($taskMetrics)
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h2 class="font-semibold text-slate-950">Authorized work-task context</h2><p class="mt-1 text-sm text-slate-500">This shows workload in your permitted outlets only. Personal tasks are excluded entirely.</p></div><a href="{{ route('tasks.team') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Open team tasks</a></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-4"><a href="{{ route('tasks.today') }}" class="rounded-lg bg-sky-50 p-4 transition hover:bg-sky-100"><p class="text-xs font-semibold text-sky-700">Due today</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ $taskMetrics['due_today'] }}</p></a><a href="{{ route('tasks.overdue') }}" class="rounded-lg bg-rose-50 p-4 transition hover:bg-rose-100"><p class="text-xs font-semibold text-rose-700">Overdue</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ $taskMetrics['overdue'] }}</p></a><a href="{{ route('tasks.team') }}" class="rounded-lg bg-amber-50 p-4 transition hover:bg-amber-100"><p class="text-xs font-semibold text-amber-700">Unassigned</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ $taskMetrics['unassigned'] }}</p></a><a href="{{ route('tasks.completed') }}" class="rounded-lg bg-emerald-50 p-4 transition hover:bg-emerald-100"><p class="text-xs font-semibold text-emerald-700">Completed today</p><p class="mt-1 text-2xl font-semibold text-slate-950">{{ $taskMetrics['completed_today'] }}</p></a></div>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Workload by outlet</p><div class="mt-3 space-y-2">@forelse($taskMetrics['by_outlet'] as $row)<div class="flex justify-between gap-3 text-sm"><span class="truncate text-slate-600">{{ $row->outlet?->name ?: 'Company-wide work' }}</span><span class="font-semibold text-slate-900">{{ $row->total }}</span></div>@empty<p class="text-sm text-slate-500">No open work tasks.</p>@endforelse</div></div>
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Assigned workload</p><div class="mt-3 space-y-2">@forelse($taskMetrics['by_assignee'] as $row)<div class="flex justify-between gap-3 text-sm"><span class="truncate text-slate-600">{{ $row->assignee?->name ?: 'Unavailable user' }}</span><span class="font-semibold text-slate-900">{{ $row->total }}</span></div>@empty<p class="text-sm text-slate-500">No assigned work tasks.</p>@endforelse</div></div>
                    <div class="rounded-lg bg-slate-50 p-4"><p class="text-xs font-semibold text-slate-500">Open work by priority</p><div class="mt-3 space-y-2">@forelse($taskMetrics['by_priority'] as $priority => $total)<div class="flex justify-between gap-3 text-sm"><span class="text-slate-600">{{ str($priority)->headline() }}</span><span class="font-semibold text-slate-900">{{ $total }}</span></div>@empty<p class="text-sm text-slate-500">No open work tasks.</p>@endforelse</div></div>
                </div>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Recent starters</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse($recent as $employee)
                        <a class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50" href="{{ route('workforce.employees.show', $employee) }}">
                            <span>
                                <span class="block font-medium text-slate-900">{{ $employee->display_name }}</span>
                                <span class="text-sm text-slate-500">{{ $employee->job_title ?: 'Job title not set' }}</span>
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ str($employee->status)->headline() }}</span>
                        </a>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-500">No employees yet. Add a profile first; login access remains optional.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-950">Employees by primary outlet</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse($outletBreakdown as $row)
                        <div class="flex items-center justify-between px-5 py-4 text-sm">
                            <span class="font-medium text-slate-800">{{ $row->primaryBranch?->name ?: 'No primary outlet' }}</span>
                            <span class="rounded-full bg-teal-50 px-2.5 py-1 font-semibold text-teal-700">{{ $row->employees }}</span>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-500">Outlet assignment data will appear after employees are added.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
