@extends('layouts.admin')

@section('title', 'Email Delivery')
@section('page-title', 'Email Delivery')
@section('breadcrumbs')<span>/</span><span>Settings</span><span>/</span><span>Email delivery</span>@endsection

@section('content')
    @php($isAdmin = auth()->user()->can('integrations.email.manage'))
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-slate-200 p-5 sm:flex-row sm:items-start sm:justify-between dark:border-slate-800">
                <div><p class="text-sm font-medium text-teal-700 dark:text-teal-300">Integrations</p><h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Email delivery</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Configure a company SMTP connection or use a server-managed SMTP service. Saved passwords are encrypted and never shown here.</p></div>
                <span class="w-fit rounded-full px-3 py-1.5 text-sm font-semibold {{ $configuration['configured'] ? 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-100' : 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-100' }}">{{ $configuration['configured'] ? 'Configured' : 'Not configured' }}</span>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><p class="text-xs font-semibold uppercase text-slate-500">Configuration</p><p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ str($configuration['source'])->headline() }}</p></div>
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><p class="text-xs font-semibold uppercase text-slate-500">Last sent</p><p class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $lastSuccess?->delivered_at?->format('d M Y, h:i A') ?? 'No successful delivery' }}</p></div>
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><p class="text-xs font-semibold uppercase text-slate-500">Last failure</p><p class="mt-2 text-sm font-semibold text-slate-950 dark:text-white">{{ $lastFailure?->failed_at?->format('d M Y, h:i A') ?? 'No failure recorded' }}</p></div>
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><p class="text-xs font-semibold uppercase text-slate-500">Queue</p><p class="mt-2 font-semibold text-slate-950 dark:text-white">Queued delivery enabled</p></div>
            </div>
            @if(!$configuration['configured'])<div class="mx-5 mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">{{ $configuration['reason'] }} Leads and demos continue to work; email delivery is recorded as skipped until SMTP is available.</div>@endif
        </section>

        @can('email.test.send')
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">Send a test email</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Send the branded delivery template to a verified address. Limited to three requests every ten minutes.</p><form method="POST" action="{{ route('settings.integrations.email.test') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">@csrf<input type="email" name="recipient" required placeholder="you@company.com" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Send test email</button></form></section>
        @endcan

        @if($isAdmin)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">Company SMTP settings</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Leave the password blank to preserve the existing saved password. Use the separate action below to remove it.</p>
                <form method="POST" action="{{ route('settings.integrations.email.update') }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf @method('PUT')
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">SMTP host<input name="host" value="{{ old('host', $setting?->host) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Port<input name="port" type="number" value="{{ old('port', $setting?->port) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Encryption<select name="encryption" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="tls" @selected(old('encryption', $setting?->encryption) === 'tls')>TLS</option><option value="ssl" @selected(old('encryption', $setting?->encryption) === 'ssl')>SSL</option><option value="none" @selected(old('encryption', $setting?->encryption) === 'none')>None</option></select></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">SMTP username<input name="username" value="{{ old('username', $setting?->username) }}" autocomplete="off" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">SMTP password<input name="password" type="password" autocomplete="new-password" placeholder="{{ $setting?->getRawOriginal('password') ? 'Saved securely; leave blank to retain' : 'Optional when your provider does not require one' }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">From name<input name="from_name" value="{{ old('from_name', $setting?->from_name) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">From email<input name="from_address" type="email" value="{{ old('from_address', $setting?->from_address) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Reply-to email<input name="reply_to_address" type="email" value="{{ old('reply_to_address', $setting?->reply_to_address) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                    <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $setting?->is_enabled ?? true))>Enable email delivery</label>
                    <div class="md:col-span-2"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Save settings</button></div>
                </form>
                <div class="mt-3 flex flex-wrap gap-3"><form method="POST" action="{{ route('settings.integrations.email.disable') }}">@csrf<button class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Disable email delivery</button></form>@if($setting?->getRawOriginal('password'))<form method="POST" action="{{ route('settings.integrations.email.password.destroy') }}" onsubmit="return confirm('Remove the saved SMTP password?')">@csrf @method('DELETE')<button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Remove saved password</button></form>@endif</div>
            </section>
        @endcan
        <a href="{{ route('settings.email-deliveries.index') }}" class="inline-flex text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">View email delivery log</a>
    </div>
@endsection
