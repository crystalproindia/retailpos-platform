@extends('layouts.guest')

@section('content')
    <div>
        <p class="text-2xl font-semibold tracking-normal text-slate-950">Forgot password</p>
        <p class="mt-2 text-sm leading-6 text-slate-500">Enter your account email and we will send a reset link.</p>
    </div>

    @if (session('status'))
        <div class="mt-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-200">
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">
            Send reset link
        </button>

        <a href="{{ route('login') }}" class="block text-center text-sm font-medium text-slate-600 hover:text-slate-950">Back to login</a>
    </form>
@endsection
