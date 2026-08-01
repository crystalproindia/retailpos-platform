@extends('layouts.admin')

@section('title', 'User accounts')
@section('page-title', 'User accounts')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('workforce.dashboard') }}">Workforce</a><span>/</span><span>User accounts</span>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-5">
        <form class="flex flex-wrap gap-2" method="GET"><input name="search" value="{{ request('search') }}" class="rounded-lg border-slate-300 text-sm" placeholder="Name or email"><select name="state" class="rounded-lg border-slate-300 text-sm"><option value="">All account states</option>@foreach(['pending_invitation', 'active', 'suspended', 'disabled'] as $state)<option value="{{ $state }}" @selected(request('state') === $state)>{{ str($state)->headline() }}</option>@endforeach</select><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700">Apply</button></form>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr><th class="p-4">Account</th><th class="p-4">Employee</th><th class="p-4">Role</th><th class="p-4">State</th><th class="p-4">Last login</th><th class="p-4"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        @php
                            $workforceRoleName = $user->workforceRole instanceof \App\Models\WorkforceRole ? $user->workforceRole->name : null;
                            $systemRoleName = $user->role instanceof \App\Enums\UserRole
                                ? $user->role->label()
                                : (string) str($user->role ?: 'staff')->headline();
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="p-4"><span class="block font-semibold text-slate-900">{{ $user->name }}</span><span class="block text-slate-500">{{ $user->email }}</span></td>
                            <td class="p-4 text-slate-600">@if ($user->employee)<a class="font-medium text-teal-700" href="{{ route('workforce.employees.show', $user->employee) }}">{{ $user->employee->display_name }}</a>@else Not linked @endif</td>
                            <td class="p-4 text-slate-600">{{ $workforceRoleName ?: $systemRoleName }}</td>
                            <td class="p-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($user->account_status)->headline() }}</span></td>
                            <td class="p-4 text-slate-600">{{ $user->last_login_at?->diffForHumans() ?: 'Never' }}</td>
                            <td class="p-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @foreach (['active' => 'Activate', 'suspended' => 'Suspend', 'disabled' => 'Disable'] as $state => $label)
                                        @if ($user->account_status !== $state)
                                            <form method="POST" action="{{ route('workforce.users.state', $user) }}">
                                                @csrf
                                                <input type="hidden" name="state" value="{{ $state }}">
                                                <button class="text-xs font-semibold {{ $state === 'disabled' ? 'text-rose-700' : 'text-teal-700' }}">{{ $label }}</button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-12 text-center text-slate-500">No user accounts match this view.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
