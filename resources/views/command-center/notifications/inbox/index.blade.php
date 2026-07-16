@extends('layouts.admin')

@section('title', 'Notifications')
@section('page-title', 'Notification Center')

@section('breadcrumbs')
    <span>/</span><span>Notifications</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Inbox</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }} for your account.</p>
                </div>
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Mark all read</button>
                </form>
            </div>

            <form method="GET" action="{{ route('notifications.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_160px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search notification text" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All statuses</option>
                    <option value="unread" @selected(request('status') === 'unread')>Unread</option>
                    <option value="read" @selected(request('status') === 'read')>Read</option>
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($notifications as $notification)
                    <article class="p-5 {{ $notification->read_at ? '' : 'bg-teal-50/50 dark:bg-teal-950/20' }}">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    @unless ($notification->read_at)
                                        <span class="size-2 rounded-full bg-teal-500"></span>
                                    @endunless
                                    <p class="font-semibold text-slate-950 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ str($notification->data['severity'] ?? 'info')->headline() }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $notification->data['message'] ?? 'Open Command Center for details.' }}</p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $notification->created_at->diffForHumans() }} · {{ $notification->data['event_key'] ?? 'event' }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if (! empty($notification->data['action_url']))
                                    <a href="{{ $notification->data['action_url'] }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Open</a>
                                @endif
                                <form method="POST" action="{{ $notification->read_at ? route('notifications.inbox.unread', $notification->id) : route('notifications.inbox.read', $notification->id) }}">
                                    @csrf
                                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ $notification->read_at ? 'Mark unread' : 'Mark read' }}</button>
                                </form>
                                <form method="POST" action="{{ route('notifications.inbox.destroy', $notification->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No notifications match the current filters.</div>
                @endforelse
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $notifications->links() }}</div>
        </section>
    </div>
@endsection
