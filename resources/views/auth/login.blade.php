@extends('layouts.guest')

@section('content')
    <div>
        <p class="text-2xl font-semibold tracking-normal text-slate-950">Sign in</p>
        <p class="mt-2 text-sm leading-6 text-slate-500">Use your RetailPOS Command Center account.</p>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-200">
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="flex items-center justify-between gap-4">
                <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-slate-950 hover:text-teal-700">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-200">
            @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-600">
            <input type="hidden" name="remember" value="0">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950">
            Remember me
        </label>

        <button type="submit" class="flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">
            Login
        </button>
    </form>
@endsection
