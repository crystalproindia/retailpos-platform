@extends('layouts.admin')

@section('title', $demo ? 'Reschedule Demo' : 'Schedule Demo')
@section('page-title', $demo ? 'Reschedule Demo' : 'Schedule Demo')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><a href="{{ route('crm.leads.show', $lead) }}" class="hover:text-slate-950 dark:hover:text-white">Lead</a><span>/</span><span>{{ $demo ? 'Reschedule' : 'Schedule Demo' }}</span>
@endsection

@section('content')
    @php
        $isReschedule = $demo !== null;
        $defaultDate = $demo?->scheduled_date?->format('Y-m-d') ?? now()->addDay()->format('Y-m-d');
        $defaultStart = $demo?->starts_at?->setTimezone($demo?->timezone ?? config('app.timezone'))->format('H:i') ?? '10:00';
        $defaultEnd = $demo?->ends_at?->setTimezone($demo?->timezone ?? config('app.timezone'))->format('H:i') ?? '10:30';
    @endphp

    <div class="mx-auto max-w-4xl space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ $lead->status?->name }}</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $isReschedule ? 'Reschedule demo' : 'Schedule demo' }}</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $lead->title }} · {{ $lead->business_name ?? $lead->contact_name ?? 'Unlinked lead' }}</p>
            </div>

            <form method="POST" action="{{ $isReschedule ? route('crm.demos.reschedule', $demo) : route('crm.demos.store', $lead) }}" class="p-5" data-demo-schedule-form>
                @csrf
                @if ($isReschedule) @method('PATCH') @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="demo_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Demo date</label>
                        <input id="demo_date" name="demo_date" type="date" value="{{ old('demo_date', $defaultDate) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('demo_date')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="assigned_to" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Sales owner</label>
                        <select id="assigned_to" name="assigned_to" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('assigned_to', $demo?->assigned_to ?? $lead->assigned_user_id ?? auth()->id()) === $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                        @error('assigned_to')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="start_time" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Start time</label>
                        <input id="start_time" name="start_time" type="time" value="{{ old('start_time', $defaultStart) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('start_time')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="end_time" class="block text-sm font-medium text-slate-700 dark:text-slate-300">End time</label>
                        <input id="end_time" name="end_time" type="time" value="{{ old('end_time', $defaultEnd) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('end_time')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="meeting_mode" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Meeting mode</label>
                        <select id="meeting_mode" name="meeting_mode" required data-demo-meeting-mode class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                            @foreach ($meetingModes as $meetingMode)
                                <option value="{{ $meetingMode->value }}" @selected(old('meeting_mode', $demo?->meeting_mode?->value ?? 'phone_call') === $meetingMode->value)>{{ $meetingMode->label() }}</option>
                            @endforeach
                        </select>
                        @error('meeting_mode')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="meeting_link" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Meeting link</label>
                        <input id="meeting_link" name="meeting_link" type="url" value="{{ old('meeting_link', $demo?->meeting_link) }}" placeholder="https://" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('meeting_link')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="customer_email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Customer email</label>
                        <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email', $demo?->customer_email ?? $lead->email) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('customer_email')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Customer phone</label>
                        <input id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone', $demo?->customer_phone ?? $lead->phone) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('customer_phone')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="timezone" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Timezone</label>
                        <input id="timezone" name="timezone" type="text" value="{{ old('timezone', $demo?->timezone ?? config('app.timezone')) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @error('timezone')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                        <textarea id="notes" name="notes" rows="4" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">{{ old('notes', $demo?->notes) }}</textarea>
                        @error('notes')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2 rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-950 dark:text-white">Google Calendar</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $googleCalendarConnected ? 'Sync this demo to the connected company calendar.' : 'Connect Google Calendar from Integrations to sync demo events.' }}</p>
                            </div>
                            @unless ($googleCalendarConnected)
                                <a href="{{ route('integrations.google.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open integration</a>
                            @endunless
                        </div>
                        @if ($googleCalendarConnected)
                            @if (! $isReschedule)
                                <input type="hidden" name="sync_to_google" value="0">
                                <label class="mt-4 flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    <input type="checkbox" name="sync_to_google" value="1" @checked(old('sync_to_google')) class="size-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950 dark:border-slate-700">
                                    Sync to Google Calendar
                                </label>
                            @elseif ($demo?->external_calendar_provider === 'google')
                                <p class="mt-4 text-sm font-medium text-teal-700 dark:text-teal-300">This synced calendar event will update automatically when the demo is rescheduled.</p>
                            @endif
                            <input type="hidden" name="create_google_meet" value="0">
                            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="create_google_meet" value="1" @checked(old('create_google_meet')) data-google-meet-checkbox class="size-4 rounded border-slate-300 text-slate-950 focus:ring-slate-950 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700">
                                Create a Google Meet link when meeting mode is Google Meet Later
                            </label>
                        @endif
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end dark:border-slate-800">
                    <a href="{{ route('crm.leads.show', $lead) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">{{ $isReschedule ? 'Save reschedule' : 'Schedule demo' }}</button>
                </div>
            </form>
        </section>
    </div>
@endsection
