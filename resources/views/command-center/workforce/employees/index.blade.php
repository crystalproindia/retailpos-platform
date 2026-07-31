@extends('layouts.admin')

@section('title', 'Employees')
@section('page-title', 'Employees')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><span>Employees</span>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form class="flex flex-1 flex-wrap gap-2" method="GET">
                <input name="search" value="{{ request('search') }}" class="min-w-48 rounded-lg border-slate-300 text-sm" placeholder="Name, code, email, or mobile">
                <select name="status" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach(['draft', 'invited', 'active', 'on_leave', 'suspended', 'inactive', 'archived'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <select name="outlet_id" class="rounded-lg border-slate-300 text-sm">
                    <option value="">All authorized outlets</option>
                    @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) request('outlet_id') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach
                </select>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Apply</button>
            </form>
            <div class="flex gap-2">
                @can('workforce.export')
                    <a href="{{ route('workforce.employees.export') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Export CSV</a>
                @endcan
                @can('workforce.manage')
                    <a href="{{ route('workforce.employees.create') }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Add employee</a>
                @endcan
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-4">Employee</th><th class="p-4">Primary outlet</th><th class="p-4">Status</th><th class="p-4">Access</th><th class="p-4"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($employees as $employee)
                        <tr class="transition hover:bg-slate-50">
                            <td class="p-4"><a class="font-semibold text-slate-900 hover:text-teal-700" href="{{ route('workforce.employees.show', $employee) }}">{{ $employee->display_name }}</a><span class="mt-0.5 block text-slate-500">{{ $employee->employee_number }}@if($employee->job_title) · {{ $employee->job_title }}@endif</span></td>
                            <td class="p-4 text-slate-600">{{ $employee->primaryBranch?->name ?: 'Unassigned' }}</td>
                            <td class="p-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($employee->status)->headline() }}</span></td>
                            <td class="p-4 text-slate-600">{{ $employee->user ? str($employee->user->account_status)->headline() : 'No login' }}</td>
                            <td class="p-4 text-right"><a class="text-sm font-semibold text-teal-700 hover:text-teal-800" href="{{ route('workforce.employees.show', $employee) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="p-12 text-center text-slate-500" colspan="5">No employee profiles match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $employees->links() }}
    </div>
@endsection
