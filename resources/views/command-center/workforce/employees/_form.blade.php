@php
    $editing = isset($employee);
    $selectedOutlets = old('outlet_ids', $editing ? $employee->outletAssignments->pluck('branch_id')->all() : []);
    $selectedWarehouses = old('warehouse_ids', $editing ? $employee->warehouseAssignments->pluck('warehouse_id')->all() : []);
    $selectedRegisters = old('register_ids', $editing ? $employee->registerAssignments->pluck('register_id')->all() : []);
@endphp
<form method="POST" action="{{ $editing ? route('workforce.employees.update', $employee) : route('workforce.employees.store') }}" class="mx-auto max-w-5xl space-y-6">
    @csrf
    @if($editing) @method('PUT') @endif
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-slate-950">{{ $editing ? 'Edit employee profile' : 'Create an employee profile' }}</h1>
        <p class="mt-2 text-sm text-slate-500">A profile can be created before a login account. Only workplace contact and operational information is stored here.</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            @foreach(['employee_number' => 'Employee code', 'first_name' => 'First name', 'last_name' => 'Last name', 'display_name' => 'Display name', 'work_email' => 'Work email', 'work_mobile' => 'Work mobile', 'job_title' => 'Job title', 'department' => 'Department'] as $field => $label)
                <label class="block text-sm font-medium text-slate-700">{{ $label }}<input name="{{ $field }}" value="{{ old($field, $employee->$field ?? '') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@error($field)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            @endforeach
            <label class="block text-sm font-medium text-slate-700">Joining date<input type="date" name="joining_date" value="{{ old('joining_date', $editing && $employee->joining_date ? $employee->joining_date->toDateString() : '') }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="block text-sm font-medium text-slate-700">Employment status<select name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@foreach(['draft', 'invited', 'active', 'on_leave', 'suspended', 'inactive'] as $status)<option value="{{ $status }}" @selected(old('status', $employee->status ?? 'draft') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
            <label class="block text-sm font-medium text-slate-700">Primary outlet<select name="primary_branch_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="">Unassigned</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('primary_branch_id', $employee->primary_branch_id ?? '') === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label class="block text-sm font-medium text-slate-700">Reporting manager<select name="reporting_manager_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="">No manager assigned</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string) old('reporting_manager_id', $employee->reporting_manager_id ?? '') === (string) $manager->id)>{{ $manager->display_name }}</option>@endforeach</select></label>
        </div>
        <label class="mt-4 block text-sm font-medium text-slate-700">Manager-only notes<textarea name="manager_notes" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">{{ old('manager_notes', $employee->manager_notes ?? '') }}</textarea><span class="mt-1 block text-xs font-normal text-slate-500">These notes do not appear in employee self-service views or exports.</span></label>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-950">Operational assignments</h2>
        <p class="mt-1 text-sm text-slate-500">Assignments are restricted to active resources in this company and become effective immediately for linked user accounts.</p>
        <div class="mt-5 grid gap-6 lg:grid-cols-3">
            <fieldset><legend class="text-sm font-semibold text-slate-700">Additional outlets</legend><div class="mt-2 max-h-44 space-y-2 overflow-y-auto">@forelse($branches as $branch)<label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="outlet_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $selectedOutlets)) class="rounded border-slate-300 text-teal-600">{{ $branch->name }}</label>@empty<p class="text-sm text-slate-500">No active outlets.</p>@endforelse</div></fieldset>
            <fieldset><legend class="text-sm font-semibold text-slate-700">Warehouses</legend><div class="mt-2 max-h-44 space-y-2 overflow-y-auto">@forelse($warehouses as $warehouse)<label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="warehouse_ids[]" value="{{ $warehouse->id }}" @checked(in_array($warehouse->id, $selectedWarehouses)) class="rounded border-slate-300 text-teal-600">{{ $warehouse->name }}</label>@empty<p class="text-sm text-slate-500">No active warehouses.</p>@endforelse</div></fieldset>
            <fieldset><legend class="text-sm font-semibold text-slate-700">POS registers</legend><div class="mt-2 max-h-44 space-y-2 overflow-y-auto">@forelse($registers as $register)<label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="register_ids[]" value="{{ $register->id }}" @checked(in_array($register->id, $selectedRegisters)) class="rounded border-slate-300 text-teal-600">{{ $register->name }}</label>@empty<p class="text-sm text-slate-500">No active registers.</p>@endforelse</div></fieldset>
        </div>
    </section>
    <div class="flex justify-end gap-3"><a href="{{ $editing ? route('workforce.employees.show', $employee) : route('workforce.employees.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</a><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">{{ $editing ? 'Save changes' : 'Save employee' }}</button></div>
</form>
