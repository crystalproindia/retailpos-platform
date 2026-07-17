<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Customer Onboarding</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Implementation progress, risks, and customers nearing go-live.</p>
        </div>
        <a href="{{ route('crm.onboarding.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Open onboarding</a>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Active Onboardings', 'value' => $onboardingMetrics['active'], 'tone' => 'bg-slate-50 dark:bg-slate-950'],
            ['label' => 'Waiting for Customer', 'value' => $onboardingMetrics['waiting_for_customer'], 'tone' => 'bg-amber-50 dark:bg-amber-950/30'],
            ['label' => 'Overdue Tasks', 'value' => $onboardingMetrics['overdue_tasks'], 'tone' => 'bg-rose-50 dark:bg-rose-950/30'],
            ['label' => 'Go-Live Ready', 'value' => $onboardingMetrics['go_live_ready'], 'tone' => 'bg-teal-50 dark:bg-teal-950/30'],
            ['label' => 'Live This Month', 'value' => $onboardingMetrics['live_this_month'], 'tone' => 'bg-sky-50 dark:bg-sky-950/30'],
        ] as $card)
            <a href="{{ route('crm.onboarding.index') }}" class="rounded-lg border border-slate-200 p-4 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-800 dark:hover:border-slate-700 {{ $card['tone'] }}"><p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $card['value'] }}</p></a>
        @endforeach
    </div>
    <div class="mt-5 grid gap-3 lg:grid-cols-2">
        @forelse ($onboardingMetrics['upcoming'] as $onboarding)
            <a href="{{ route('crm.onboarding.show', $onboarding) }}" class="rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $onboarding->business_name ?: $onboarding->customer?->company_name }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $onboarding->onboarding_number }} · {{ $onboarding->implementationOwner?->name ?? 'Unassigned' }}</p></div><div class="text-right"><p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $onboarding->progress_percent }}%</p><p class="mt-1 text-xs text-slate-500">{{ $onboarding->target_go_live_date?->format('d M') }}</p></div></div></a>
        @empty
            <p class="lg:col-span-2 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No active onboarding has a target go-live date yet.</p>
        @endforelse
    </div>
    <div class="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
        <p class="text-sm font-semibold text-slate-950 dark:text-white">Recently started</p>
        <div class="mt-3 grid gap-3 lg:grid-cols-2">
            @forelse ($onboardingMetrics['recent'] as $onboarding)
                <a href="{{ route('crm.onboarding.show', $onboarding) }}" class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><div><p class="font-medium text-slate-950 dark:text-white">{{ $onboarding->business_name ?: $onboarding->customer?->company_name }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $onboarding->onboarding_number }} · {{ $onboarding->created_at?->diffForHumans() }}</p></div><span class="text-sm font-semibold text-slate-950 dark:text-white">{{ $onboarding->progress_percent }}%</span></a>
            @empty
                <p class="lg:col-span-2 text-sm text-slate-500 dark:text-slate-400">No onboarding records have been started yet.</p>
            @endforelse
        </div>
    </div>
</section>
