@php
    $scoreTone = match ($aiScore?->category?->value) {
        'hot', 'at_risk' => 'border-rose-200 bg-rose-50 dark:border-rose-950 dark:bg-rose-950/20',
        'warm' => 'border-amber-200 bg-amber-50 dark:border-amber-950 dark:bg-amber-950/20',
        'won' => 'border-emerald-200 bg-emerald-50 dark:border-emerald-950 dark:bg-emerald-950/20',
        default => 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900',
    };
@endphp

<section class="rounded-lg border p-5 shadow-sm {{ $scoreTone }}">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            <div class="flex size-20 shrink-0 items-center justify-center rounded-full border-8 border-slate-100 bg-white text-xl font-semibold text-slate-950 dark:border-slate-800 dark:bg-slate-950 dark:text-white">
                {{ $aiScore?->score ?? '—' }}
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">AI Lead Assistant</h2>
                    @if ($aiScore)
                        <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ $aiScore->category->label() }}</span>
                        <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-950 dark:text-slate-300">{{ $aiScore->priority->label() }} priority</span>
                        <span class="rounded-full bg-white/80 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-950 dark:text-slate-300">{{ $aiScore->confidence->label() }} confidence</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Transparent rule-based guidance from CRM activity, deal progress, and follow-up timing. It does not send messages automatically.</p>
                @if ($aiScore)
                    <p class="mt-3 text-sm font-medium text-slate-950 dark:text-white">Next best action: {{ $aiScore->next_best_action }}</p>
                @else
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Analyze this lead to create its first score snapshot.</p>
                @endif
            </div>
        </div>
        @can('crm.ai.refresh_score')
            <form method="POST" action="{{ route('crm.leads.ai.analyze', $lead) }}">@csrf<button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Analyze lead</button></form>
        @endcan
    </div>

    @if ($aiScore)
        <div class="mt-5 grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-white/70 bg-white/75 p-4 dark:border-slate-800 dark:bg-slate-950/60"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Why this score</p><ul class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-200">@forelse($aiScore->reasons ?? [] as $reason)<li>{{ $reason }}</li>@empty<li>No positive signals are recorded yet.</li>@endforelse</ul></div>
            <div class="rounded-lg border border-white/70 bg-white/75 p-4 dark:border-slate-800 dark:bg-slate-950/60"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Risks</p><ul class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-200">@forelse($aiScore->risks ?? [] as $risk)<li>{{ $risk }}</li>@empty<li>No material risk is currently detected.</li>@endforelse</ul></div>
            <div class="rounded-lg border border-white/70 bg-white/75 p-4 dark:border-slate-800 dark:bg-slate-950/60"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Opportunity</p><ul class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-200">@forelse($aiScore->opportunities ?? [] as $opportunity)<li>{{ $opportunity }}</li>@empty<li>Keep the next sales commitment specific and owned.</li>@endforelse</ul></div>
        </div>
    @endif

    @can('crm.ai.generate')
        <form method="POST" action="{{ route('crm.leads.ai.follow-up', $lead) }}" class="mt-5 grid gap-3 border-t border-slate-200 pt-5 md:grid-cols-4 dark:border-slate-800">
            @csrf
            <select name="message_type">@foreach (\App\Enums\Crm\FollowUpMessageType::cases() as $type)<option value="{{ $type->value }}" @selected(old('message_type', $aiFollowUp['options']['message_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
            <select name="tone"><option value="professional">Professional</option><option value="friendly">Friendly</option><option value="short">Short</option><option value="premium">Premium</option><option value="tamil_english">Tamil-English</option></select>
            <select name="length"><option value="short">Short</option><option value="normal" selected>Normal</option><option value="detailed">Detailed</option></select>
            <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 dark:bg-teal-300 dark:text-slate-950">Generate follow-up</button>
        </form>
    @endcan

    @if ($aiFollowUp)
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $aiFollowUp['subject'] }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Review before using. No message has been sent.</p></div><button type="button" data-copy-text="{{ $aiFollowUp['message'] }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Copy</button></div>
            <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $aiFollowUp['message'] }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                @if ($aiFollowUp['whatsapp_url'])
                    <a href="{{ $aiFollowUp['whatsapp_url'] }}" target="_blank" rel="noreferrer" class="text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open WhatsApp</a>
                @endif
                @if ($aiFollowUp['email_url'])
                    <a href="{{ $aiFollowUp['email_url'] }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Open email</a>
                @endif
            </div>
        </div>
    @endif
</section>
