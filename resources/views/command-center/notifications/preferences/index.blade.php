@extends('layouts.admin')

@section('title', 'Notification Preferences')
@section('page-title', 'Notification Preferences')

@section('breadcrumbs')
    <span>/</span><span>Notifications</span><span>/</span><span>Preferences</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Preferences</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Choose how operational events reach you. WhatsApp, SMS, and Push are reserved for future channel adapters.</p>
                </div>
                <form method="POST" action="{{ route('notifications.preferences.reset') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Reset defaults</button>
                </form>
            </div>
        </section>

        <form method="POST" action="{{ route('notifications.preferences.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @foreach ($groups as $category => $events)
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 class="font-semibold text-slate-950 dark:text-white">{{ $category }}</h2>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($events as $event)
                            @php
                                $preference = $event['preference'];
                                $definition = $event['definition'];
                                $defaultChannels = $definition['default_channels'] ?? ['database'];
                                $start = $preference?->quiet_hours_start ? substr((string) $preference->quiet_hours_start, 0, 5) : '';
                                $end = $preference?->quiet_hours_end ? substr((string) $preference->quiet_hours_end, 0, 5) : '';
                            @endphp
                            <div class="grid gap-4 p-5 lg:grid-cols-[1fr_220px_280px] lg:items-start">
                                <div>
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $definition['name'] }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $definition['description'] }}</p>
                                    <p class="mt-2 text-xs text-slate-400">{{ $event['event_key'] }}</p>
                                </div>
                                <div class="grid gap-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="preferences[{{ $event['event_key'] }}][database_enabled]" value="1" class="rounded border-slate-300" @checked($preference?->database_enabled ?? in_array('database', $defaultChannels, true))>
                                        <span>In-app</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="preferences[{{ $event['event_key'] }}][email_enabled]" value="1" class="rounded border-slate-300" @checked($preference?->email_enabled ?? in_array('email', $defaultChannels, true))>
                                        <span>Email</span>
                                    </label>
                                    @foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'push' => 'Push'] as $channel => $label)
                                        <label class="flex items-center gap-2 text-slate-400">
                                            <input type="checkbox" disabled class="rounded border-slate-300">
                                            <span>{{ $label }} <span class="text-xs">(future)</span></span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="grid gap-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="preferences[{{ $event['event_key'] }}][quiet_hours_enabled]" value="1" class="rounded border-slate-300" @checked($preference?->quiet_hours_enabled)>
                                        <span>Respect quiet hours for email</span>
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="time" name="preferences[{{ $event['event_key'] }}][quiet_hours_start]" value="{{ $start }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        <input type="time" name="preferences[{{ $event['event_key'] }}][quiet_hours_end]" value="{{ $end }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    </div>
                                    <input name="preferences[{{ $event['event_key'] }}][timezone]" value="{{ $preference?->timezone ?? config('app.timezone') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="sticky bottom-4 flex justify-end">
                <button class="rounded-lg bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save preferences</button>
            </div>
        </form>
    </div>
@endsection
