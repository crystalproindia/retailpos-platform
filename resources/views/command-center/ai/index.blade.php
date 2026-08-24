@extends('layouts.admin')
@section('title', 'AI Assistant')
@section('page-title', 'AI Assistant')
@section('content')
@php
    $money = fn ($value) => $value === null ? 'Unavailable' : 'INR '.number_format(((int) $value) / 100, 2);
    $displayFact = function (array $fact) use ($money): string {
        return match ($fact['format']) {
            'money' => $money($fact['value']),
            'percent' => $fact['value'] === null ? 'Unavailable' : number_format((float) $fact['value'], 2).'%',
            'quantity' => number_format((float) $fact['value'], 3),
            'number' => $fact['value'] === null ? 'Unavailable' : number_format((float) $fact['value'], is_float($fact['value']) ? 2 : 0),
            default => (string) ($fact['value'] ?? 'Unavailable'),
        };
    };
    $hour = now(auth()->user()->company?->timezone ?: config('app.timezone'))->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $quickPrompts = ['How are sales today?', 'What needs my attention?', 'Show slow-moving stock', 'Why did profit change?', 'What should I reorder?', 'Which customers should we follow up with?'];
@endphp

<div class="mx-auto max-w-7xl space-y-6" data-ai-assistant>
    <header class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="min-w-0"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300"><x-icon name="ai" class="size-5" /></span><div><p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $greeting }}, {{ auth()->user()->name }}</p><h1 class="text-2xl font-semibold text-slate-950 dark:text-white">What would you like to know about your business?</h1></div></div><p class="mt-4 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Ask in everyday language. I use your authorized RetailPOS reports and stock intelligence, then explain the verified facts clearly.</p></div>
            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300"><span class="font-semibold text-slate-900 dark:text-white">Read and advise only</span><span class="mt-1 block">No records or transactions can be changed here.</span></div>
        </div>
    </header>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(20rem,0.7fr)]">
        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">Your business today</p><h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">{{ $brief['summary'] }}</h2></div><x-icon name="analytics" class="size-5 shrink-0 text-teal-600 dark:text-teal-300" /></div><dl class="mt-5 grid gap-3 sm:grid-cols-2">@foreach($brief['facts'] as $fact)<div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"><dt class="text-xs font-semibold uppercase text-slate-500">{{ $fact['label'] }}</dt><dd class="mt-2 break-words text-lg font-semibold text-slate-950 dark:text-white">{{ $displayFact($fact) }}</dd></div>@endforeach</dl><p class="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $brief['coverage'] }}</p></article>
        <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"><p class="text-xs font-semibold uppercase text-amber-700 dark:text-amber-300">Needs your attention</p><h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">Worth checking</h2><div class="mt-5 space-y-3">@foreach(array_slice($brief['recommendations'], 0, 4) as $recommendation)<div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/70 p-3 text-sm leading-6 text-amber-950 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-100"><x-icon name="activity" class="mt-1 size-4 shrink-0" /><p>{{ $recommendation }}</p></div>@endforeach</div></aside>
    </section>

    <section id="conversation" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="assistant-heading">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6"><h2 id="assistant-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Ask RetailPOS AI</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Figures come from approved RetailPOS data providers. Explanations never replace the underlying reports.</p></div>
        <div class="p-5 sm:p-6">
            <form method="POST" action="{{ route('ai.ask') }}" class="space-y-4" data-ai-question-form>@csrf
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_13rem_auto]">
                    <label class="sr-only" for="ai-question">Ask RetailPOS AI</label><textarea id="ai-question" name="question" rows="2" maxlength="{{ config('ai.max_question_length', 500) }}" required placeholder="Ask RetailPOS AI..." class="min-h-14 resize-none rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">{{ old('question') }}</textarea>
                    <label class="grid gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300" for="ai-outlet">Outlet scope<select id="ai-outlet" name="outlet_id" class="min-h-14 rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">Current authorized scope</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}">{{ $outlet->name }}</option>@endforeach</select></label>
                    <button class="inline-flex min-h-14 items-center justify-center gap-2 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-teal-500/20 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200"><x-icon name="send" class="size-4" /> Ask</button>
                </div>
                @error('question')<p class="text-sm text-rose-700 dark:text-rose-300">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2" aria-label="Suggested questions">@foreach($quickPrompts as $prompt)<button type="button" data-ai-prompt="{{ $prompt }}" class="min-h-10 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-semibold text-slate-700 transition hover:border-teal-400 hover:text-teal-700 focus:outline-none focus:ring-4 focus:ring-teal-500/15 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-teal-600">{{ $prompt }}</button>@endforeach</div>
                <p class="hidden text-sm text-slate-500" role="status" aria-live="polite" data-ai-loading>Checking your authorized business data...</p>
            </form>

            @if($answer)
                <div class="mt-6 space-y-4" aria-live="polite"><div class="ml-auto max-w-3xl rounded-lg bg-slate-950 px-4 py-3 text-sm text-white dark:bg-teal-300 dark:text-slate-950"><p>{{ $question }}</p></div>
                    <article class="max-w-4xl rounded-lg border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-950 sm:p-6"><p class="text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">{{ str($answer['intent'])->replace('_', ' ')->headline() }} · {{ $answer['period']['label'] }}</p><h3 class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">{{ $answer['title'] }}</h3><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $answer['summary'] }}</p>
                        @if($answer['facts'])<dl class="mt-5 grid gap-3 sm:grid-cols-2">@foreach($answer['facts'] as $fact)<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"><dt class="text-xs font-semibold uppercase text-slate-500">{{ $fact['label'] }}</dt><dd class="mt-2 break-words text-lg font-semibold text-slate-950 dark:text-white">{{ $displayFact($fact) }}</dd></div>@endforeach</dl>@endif
                        @if($answer['recommendations'])<div class="mt-5"><h4 class="text-sm font-semibold text-slate-900 dark:text-white">What you can do</h4><ul class="mt-2 space-y-2 text-sm leading-6 text-slate-600 dark:text-slate-300">@foreach($answer['recommendations'] as $item)<li class="flex gap-2"><span class="mt-2 size-1.5 shrink-0 rounded-full bg-teal-500"></span><span>{{ $item }}</span></li>@endforeach</ul></div>@endif
                        <div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700"><p class="text-xs leading-5 text-slate-500 dark:text-slate-400"><span class="font-semibold text-slate-700 dark:text-slate-200">Coverage:</span> {{ $answer['coverage'] }}</p>@if($answer['sources'])<div class="mt-3 flex flex-wrap gap-2"><span class="text-xs font-semibold text-slate-500">Based on</span>@foreach($answer['sources'] as $source)<a href="{{ $source['url'] }}" class="text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $source['label'] }}</a>@endforeach</div>@endif</div>
                    </article><div class="flex flex-wrap gap-2">@foreach($answer['followups'] as $followup)<button type="button" data-ai-prompt="{{ $followup }}" class="min-h-10 rounded-full border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800 transition hover:border-teal-400 focus:outline-none focus:ring-4 focus:ring-teal-500/15 dark:border-teal-900 dark:bg-teal-950/30 dark:text-teal-200">{{ $followup }}</button>@endforeach</div>
                </div>
            @endif
        </div>
    </section>

    <details class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><summary class="cursor-pointer list-none p-5 text-sm font-semibold text-slate-900 focus:outline-none focus:ring-4 focus:ring-inset focus:ring-teal-500/15 dark:text-white sm:p-6">Forecast history and controls</summary><div class="border-t border-slate-200 p-5 dark:border-slate-800 sm:p-6"><div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><h2 class="font-semibold text-slate-950 dark:text-white">Explainable deterministic forecasts</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Existing forecasts remain available and never perform business actions.</p></div>@can('ai.forecasts.run')<form method="POST" action="{{ route('ai.run') }}" class="flex flex-wrap gap-2">@csrf<select name="type" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="all">Refresh all</option><option value="sales">Sales</option><option value="inventory">Inventory</option><option value="customers">Customers</option><option value="crm">CRM priorities</option></select><button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">Recalculate</button></form>@endcan</div><div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($data['latest_runs'] as $run)<article class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><div class="flex justify-between gap-3"><p class="font-medium text-slate-900 dark:text-white">{{ str($run->forecast_type)->headline() }}</p><span class="text-xs font-semibold uppercase text-slate-500">{{ str($run->status)->replace('_', ' ') }}</span></div><p class="mt-2 text-xs text-slate-500">{{ number_format($run->data_points) }} data points · {{ $run->confidence_level ? str($run->confidence_level)->headline() : 'No confidence rating' }}</p></article>@empty<p class="text-sm text-slate-500">No forecast history yet.</p>@endforelse</div></div></details>
    <p class="px-2 text-xs leading-5 text-slate-500 dark:text-slate-400">AI insights are based on your authorized RetailPOS data and help with decisions. Review important business actions before proceeding.@unless($providerConfigured) Plain-language deterministic answers are active; optional provider-enhanced wording is not configured.@endunless</p>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('#ai-question');
    document.querySelectorAll('[data-ai-prompt]').forEach((button) => button.addEventListener('click', () => {
        input.value = button.dataset.aiPrompt;
        input.focus();
        input.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
    }));
    document.querySelector('[data-ai-question-form]')?.addEventListener('submit', () => document.querySelector('[data-ai-loading]')?.classList.remove('hidden'));
});
</script>
@endpush
@endsection
