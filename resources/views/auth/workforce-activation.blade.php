@extends('layouts.guest')

@section('content')
    <div class="mx-auto w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-semibold text-teal-700">RetailPOS Workforce</p>
        <h1 class="mt-1 text-2xl font-semibold text-slate-950">Activate your account</h1>
        <p class="mt-2 text-sm text-slate-500">Welcome, {{ $invitation->employee->display_name }}. Choose a password to activate your secure access.</p>
        <form method="POST" action="{{ route('workforce.invitation.accept', ['token' => $token]) }}" class="mt-6 space-y-4">@csrf
            <label class="block text-sm font-medium text-slate-700">New password<input name="password" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-slate-300" required autofocus></label>
            <label class="block text-sm font-medium text-slate-700">Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-slate-300" required></label>
            @error('invitation')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
            <button class="w-full rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Activate account</button>
        </form>
    </div>
@endsection
