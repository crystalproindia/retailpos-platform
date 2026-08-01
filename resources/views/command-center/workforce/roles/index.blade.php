@extends('layouts.admin')

@section('title', 'Workforce roles')
@section('page-title', 'Workforce roles')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><span>Roles</span>
@endsection

@section('content')
    <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h1 class="text-lg font-semibold text-slate-950">Create a custom role</h1>
            <p class="mt-2 text-sm text-slate-500">System Administrator remains protected. A custom role only receives the permissions explicitly selected below; its name does not grant access.</p>
            <form method="POST" action="{{ route('workforce.roles.store') }}" class="mt-5 space-y-4">@csrf
                <label class="block text-sm font-medium text-slate-700">Role name<input name="name" value="{{ old('name') }}" class="mt-1 block w-full rounded-lg border-slate-300"></label>
                <label class="block text-sm font-medium text-slate-700">Compatible base access<select name="base_role" class="mt-1 block w-full rounded-lg border-slate-300">@foreach($baseRoles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
                <label class="block text-sm font-medium text-slate-700">Description<textarea name="description" rows="2" class="mt-1 block w-full rounded-lg border-slate-300">{{ old('description') }}</textarea></label>
                <fieldset><legend class="text-sm font-semibold text-slate-700">Permission matrix</legend><p class="mt-1 text-xs text-slate-500">Permissions are checked on the server. Choose only capabilities this role needs.</p><div class="mt-3 max-h-80 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3">@foreach ($permissions as $permission => $roleNames)<label class="flex items-start gap-2 rounded p-1 text-sm text-slate-700 hover:bg-slate-50"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', []))) class="mt-0.5 rounded border-slate-300 text-teal-600"><span><span class="font-medium">{{ str($permission)->replace('.', ' ')->headline() }}</span><span class="block text-xs text-slate-500">Available in the {{ implode(', ', $roleNames) }} system role policy.</span></span></label>@endforeach</div></fieldset>
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Create custom role</button>
            </form>
        </section>
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 p-5"><h2 class="font-semibold text-slate-950">Tenant custom roles</h2><p class="mt-1 text-sm text-slate-500">Standard application roles remain immutable and continue to protect existing production users.</p></div><div class="divide-y divide-slate-100">@forelse($roles as $role)<div class="p-5"><div class="flex flex-wrap justify-between gap-3"><div><p class="font-semibold text-slate-900">{{ $role->name }}</p><p class="mt-1 text-sm text-slate-500">{{ str($role->base_role)->headline() }} base access · {{ $role->users_count }} assigned users</p></div><span class="rounded-full {{ $role->is_active ? 'bg-teal-50 text-teal-700' : 'bg-slate-100 text-slate-600' }} px-2.5 py-1 text-xs font-semibold">{{ $role->is_active ? 'Active' : 'Inactive' }}</span></div><p class="mt-3 text-sm text-slate-600">{{ $role->description ?: 'No description provided.' }}</p><p class="mt-3 text-xs text-slate-500">{{ $role->permissions->count() }} explicit permissions</p></div>@empty<div class="p-10 text-center text-sm text-slate-500">No custom roles yet. Standard roles remain in use.</div>@endforelse</div></section>
    </div>
@endsection
