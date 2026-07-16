<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ $heading }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The next confirmed conversations in your sales calendar.</p>
        </div>
        <a href="{{ route('crm.demo-requests.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">View demo requests</a>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($upcomingDemos as $demo)
            @php
                $statusTone = match ($demo->status?->tone()) {
                    'success' => 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-100',
                    'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
                    'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-100',
                    default => 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-100',
                };
            @endphp
            <a href="{{ route('crm.leads.show', $demo->lead) }}" class="block rounded-lg border border-slate-200 p-4 transition hover:border-slate-300 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-950 dark:text-white">{{ $demo->lead?->business_name ?? $demo->lead?->title ?? 'Unlinked lead' }}</p>
                        <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ $demo->lead?->title }}</p>
                    </div>
                    <span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $demo->status?->label() }}</span>
                </div>
                <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-3 dark:text-slate-300">
                    <p><span class="font-medium text-slate-800 dark:text-slate-100">When:</span> {{ $demo->starts_at?->setTimezone($demo->timezone)->format('d M Y, h:i A') }}</p>
                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Owner:</span> {{ $demo->assignedTo?->name ?? 'Unassigned' }}</p>
                    <p><span class="font-medium text-slate-800 dark:text-slate-100">Mode:</span> {{ $demo->meeting_mode?->label() }}</p>
                </div>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center dark:border-slate-700">
                <p class="font-medium text-slate-700 dark:text-slate-200">{{ $emptyHeading }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $emptyMessage }}</p>
            </div>
        @endforelse
    </div>
</section>
