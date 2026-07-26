@extends('layouts.guest')

@section('title', 'Verify your account')
@section('content')
    <div class="space-y-6"><div><h1 class="text-2xl font-semibold text-slate-950">Verify your account</h1><p class="mt-2 text-sm text-slate-500">Verify either your email or mobile number to finish setting up your account.</p></div>
    @if($user->email && !str_ends_with($user->email, '@pending.retailpos.local'))<form method="POST" action="{{ route('account.verification.verify') }}" class="space-y-4">@csrf<input type="hidden" name="channel" value="email"><label class="block text-sm font-medium">Email verification code<input required name="code" inputmode="numeric" maxlength="6" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label><button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Verify email</button></form><form method="POST" action="{{ route('account.verification.resend') }}">@csrf<input type="hidden" name="channel" value="email"><button class="text-sm font-semibold text-slate-700">Send another email code</button></form>@endif
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"><p class="font-medium text-slate-900">Mobile verification</p><p class="mt-1">Your mobile is recorded as {{ $user->mobile ?: 'unavailable' }}. SMS OTP delivery will be enabled when a provider is configured.</p></div></div>
@endsection
