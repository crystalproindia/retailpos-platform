@extends('layouts.admin')

@section('title', 'Google Calendar Integration')
@section('page-title', 'Google Calendar')

@section('breadcrumbs')
    <span>/</span><span>Integrations</span><span>/</span><span>Google Calendar</span>
@endsection

@section('content')
    @php
        $connected = $connection?->isConnected() ?? false;
        $calendarId = old('calendar_id', $connection?->settings['calendar_id'] ?? 'primary');
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 border-b border-slate-200 p-5 md:flex-row md:items-start dark:border-slate-800">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Integrations</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Google Calendar Integration</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Connect a company Google Calendar to sync scheduled demos and optionally create Google Meet links. OAuth tokens are encrypted at rest.</p>
                </div>
                <span class="w-fit rounded-full px-3 py-1.5 text-sm font-semibold {{ $connected ? 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-100' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }}">{{ $connected ? 'Connected' : 'Not connected' }}</span>
            </div>

            <div class="grid gap-5 p-5 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Connected account</p>
                    <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $connection?->account_email ?? 'No Google account connected' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Last calendar sync</p>
                    <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $connection?->last_synced_at?->format('d M Y, h:i A') ?? 'No calendar events synced yet' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Calendar and timezone</p>
                    <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $connection?->settings['calendar_id'] ?? 'Not selected' }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $connection?->settings['timezone'] ?? config('app.timezone') }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Latest sync status</p>
                    <p class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $connection?->last_sync_status ? str($connection->last_sync_status)->replace('_', ' ')->headline() : 'No result recorded' }}</p>
                    @if($connection?->last_sync_error)<p class="mt-1 text-sm text-rose-700 dark:text-rose-300">{{ $connection->last_sync_error }}</p>@endif
                </div>
            </div>

            @if (! $configured)
                <div class="mx-5 mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">Google Calendar credentials are not configured for this environment. Set the required server environment variables before connecting an account.</div>
            @endif

            <div class="flex flex-wrap gap-3 border-t border-slate-200 p-5 dark:border-slate-800">
                @if (! $connected)
                    <form method="GET" action="{{ route('integrations.google.connect') }}">
                        <button @disabled(! $configured) class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Connect Google</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('integrations.google.test') }}">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Test connection</button>
                    </form>
                    <form method="POST" action="{{ route('integrations.google.disconnect') }}">
                        @csrf
                        <button class="rounded-lg border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950">Disconnect</button>
                    </form>
                @endif
            </div>
        </section>

        @if ($connected)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Calendar Settings</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use <span class="font-mono">primary</span> for the connected account&apos;s default calendar, or enter a shared calendar ID.</p>
                <form method="POST" action="{{ route('integrations.google.settings.update') }}" class="mt-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    @csrf
                    @method('PUT')
                    <label class="block min-w-0 flex-1 text-sm font-medium text-slate-700 dark:text-slate-300">Calendar ID
                        <input name="calendar_id" value="{{ $calendarId }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="block min-w-0 text-sm font-medium text-slate-700 dark:text-slate-300">Timezone
                        <input name="timezone" value="{{ old('timezone', $connection?->settings['timezone'] ?? config('app.timezone')) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save calendar</button>
                </form>
            </section>
        @endif
    </div>
@endsection
