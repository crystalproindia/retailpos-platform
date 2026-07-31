@extends('layouts.admin')

@section('title', 'Create user account')
@section('page-title', 'Create user account')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><a href="{{ route('workforce.employees.show', $employee) }}">{{ $employee->display_name }}</a><span>/</span><span>Account</span>
@endsection

@section('content')
    <form method="POST" action="{{ route('workforce.users.store', $employee) }}" class="mx-auto max-w-3xl space-y-6">
        @csrf
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-xl font-semibold text-slate-950">Create login for {{ $employee->display_name }}</h1>
            <p class="mt-2 text-sm text-slate-500">This direct option is for an administrator-controlled setup. The password is never stored in readable form. For an employee-led setup, use the secure invitation on the profile instead.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-slate-700">Account name<input name="name" value="{{ old('name', $employee->display_name) }}" class="mt-1 block w-full rounded-lg border-slate-300" required></label>
                <label class="text-sm font-medium text-slate-700">Work email<input name="email" type="email" value="{{ old('email', $employee->work_email) }}" class="mt-1 block w-full rounded-lg border-slate-300" required></label>
                <label class="text-sm font-medium text-slate-700">Mobile<input name="mobile" value="{{ old('mobile', $employee->work_mobile) }}" class="mt-1 block w-full rounded-lg border-slate-300"></label>
                <label class="text-sm font-medium text-slate-700">Default outlet<select name="branch_id" class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Use employee primary outlet</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $employee->primary_branch_id) === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-slate-700">Base access role<select name="role" class="mt-1 block w-full rounded-lg border-slate-300">@foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
                <label class="text-sm font-medium text-slate-700">Custom role (optional)<select name="workforce_role_id" class="mt-1 block w-full rounded-lg border-slate-300"><option value="">Use standard role permissions</option>@foreach($customRoles as $role)<option value="{{ $role->id }}">{{ $role->name }} ({{ str($role->base_role)->headline() }})</option>@endforeach</select></label>
                <label class="text-sm font-medium text-slate-700">Temporary password<input name="password" type="password" class="mt-1 block w-full rounded-lg border-slate-300" required autocomplete="new-password"></label>
                <label class="text-sm font-medium text-slate-700">Confirm password<input name="password_confirmation" type="password" class="mt-1 block w-full rounded-lg border-slate-300" required autocomplete="new-password"></label>
            </div>
        </section>
        <div class="flex justify-end gap-3"><a href="{{ route('workforce.employees.show', $employee) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</a><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Create account</button></div>
    </form>
@endsection
