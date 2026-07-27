<section class="rounded-lg border border-teal-200 bg-white p-5 shadow-sm dark:border-teal-900 dark:bg-slate-900">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div><span class="inline-flex rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">Free 365 setup</span><h2 class="mt-3 text-base font-semibold text-slate-950 dark:text-white">Make your store ready for billing</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $free365Onboarding['completed'] }} of {{ $free365Onboarding['total'] }} useful setup steps complete. This never blocks ordinary work.</p></div>
        <form method="POST" action="{{ route('free365-onboarding.dismiss') }}">@csrf<button class="text-sm font-semibold text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Dismiss</button></form>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($free365Onboarding['items'] as $item)
            @php($classes = $item['complete'] ? 'border-teal-200 bg-teal-50/60 dark:border-teal-900 dark:bg-teal-950/20' : 'border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950')
            <article class="rounded-lg border p-4 {{ $classes }}"><div class="flex items-start gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full {{ $item['complete'] ? 'bg-teal-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}" aria-label="{{ $item['complete'] ? 'Complete' : 'Not yet complete' }}">{{ $item['complete'] ? '✓' : '·' }}</span><div class="min-w-0"><p class="font-semibold text-slate-950 dark:text-white">{{ $item['label'] }}</p><p class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $item['description'] }}</p>@if(! $item['complete'] && $item['route'])<a href="{{ route($item['route']) }}" class="mt-3 inline-flex text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Continue</a>@endif</div></div></article>
        @endforeach
    </div>
</section>
