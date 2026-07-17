@php
    $lead = $card['lead'];
    $stage = $card['stage'];
    $style = $stageStyles[$stage->value];
    $whatsAppNumber = $lead->phone ? preg_replace('/\D+/', '', $lead->phone) : null;
@endphp

<article
    data-pipeline-card
    data-lead-id="{{ $lead->id }}"
    data-stage="{{ $stage->value }}"
    draggable="true"
    class="pipeline-card rounded-lg border bg-white p-4 shadow-sm transition dark:bg-slate-900 {{ $style['card'] }} {{ $stage->value === 'lost' ? 'opacity-75' : '' }}"
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('crm.leads.show', $lead) }}" class="block truncate font-semibold text-slate-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">{{ $lead->title }}</a>
            <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? 'Unassigned account' }}</p>
        </div>
        <span class="shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold {{ match($lead->priority?->value) { 'urgent' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200', 'high' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200', 'low' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200', default => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200' } }}">{{ $lead->priority?->label() ?? 'Medium' }}</span>
    </div>

    <div class="mt-4 flex items-end justify-between gap-3">
        <div>
            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Deal value</p>
            <p class="mt-1 text-base font-semibold text-slate-950 dark:text-white">{{ $card['currency'] }} {{ number_format((float) $card['value'], 0) }}</p>
        </div>
        @if ($lead->source)
            <span class="max-w-[9rem] truncate rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $lead->source->name }}</span>
        @endif
    </div>

    @if ($card['ai_score'])
        <div class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs dark:bg-slate-950">
            <span class="font-semibold text-slate-700 dark:text-slate-200">AI {{ $card['ai_score']->score }}/100</span>
            <span class="rounded-full px-2 py-0.5 font-semibold {{ match($card['ai_score']->category->value) { 'hot', 'at_risk' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200', 'warm' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200', 'won' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200', default => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200' } }}">{{ $card['ai_score']->category->label() }}</span>
        </div>
        <p class="mt-2 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $card['ai_score']->next_best_action }}</p>
    @endif

    @if ($lead->assignedUser || $lead->next_follow_up_at || $card['latest_activity'])
        <div class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
            @if ($lead->assignedUser)
                <p class="truncate text-slate-600 dark:text-slate-300"><span class="text-slate-400 dark:text-slate-500">Owner</span> {{ $lead->assignedUser->name }}</p>
            @endif
            @if ($lead->next_follow_up_at)
                <p class="{{ $card['is_overdue'] ? 'font-semibold text-rose-700 dark:text-rose-300' : ($card['is_due_today'] ? 'font-semibold text-amber-700 dark:text-amber-300' : 'text-slate-600 dark:text-slate-300') }}"><span class="text-slate-400 dark:text-slate-500">Follow-up</span> {{ $lead->next_follow_up_at->format('d M, h:i A') }}{{ $card['is_overdue'] ? ' overdue' : '' }}</p>
            @endif
            @if ($card['latest_activity'])
                <p class="truncate text-slate-500 dark:text-slate-400">{{ $card['latest_activity']->subject }}</p>
            @endif
        </div>
    @endif

    @if ($card['latest_demo'] || $card['latest_quotation'] || $card['latest_proforma'] || $card['payment_label'])
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($card['latest_demo']?->isActive())
                <span class="rounded-full bg-indigo-100 px-2 py-1 text-[11px] font-semibold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">Demo {{ $card['latest_demo']->starts_at?->format('d M') }}</span>
            @endif
            @if ($card['latest_quotation'])
                <a href="{{ route('crm.quotations.show', $card['latest_quotation']) }}" class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-200">{{ $card['latest_quotation']->quotation_number }}</a>
            @endif
            @if ($card['latest_proforma'])
                <a href="{{ route('crm.proformas.show', $card['latest_proforma']) }}" class="rounded-full bg-teal-100 px-2 py-1 text-[11px] font-semibold text-teal-800 hover:bg-teal-200 dark:bg-teal-950 dark:text-teal-200">{{ $card['latest_proforma']->proforma_number }}</a>
            @endif
            @if ($card['payment_label'])
                <span class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ $card['payment_label'] }}</span>
            @endif
            @if ($card['active_onboarding'])
                <a href="{{ route('crm.onboarding.show', $card['active_onboarding']) }}" class="rounded-full bg-violet-100 px-2 py-1 text-[11px] font-semibold text-violet-800 hover:bg-violet-200 dark:bg-violet-950 dark:text-violet-200">Onboarding {{ $card['active_onboarding']->progress_percent }}%</a>
            @endif
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-xs font-semibold dark:border-slate-800">
        <a href="{{ route('crm.leads.show', $lead) }}" class="text-teal-700 hover:text-teal-900 dark:text-teal-300">Open</a>
        @if ($lead->phone)
            <a href="tel:{{ $lead->phone }}" class="text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Call</a>
        @endif
        @if ($whatsAppNumber)
            <a href="https://wa.me/{{ $whatsAppNumber }}" target="_blank" rel="noreferrer" class="text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">WhatsApp</a>
        @endif
        @if ($lead->crmCustomer)
            <a href="{{ route('crm.customers.show', $lead->crmCustomer) }}" class="text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Customer</a>
            @if (! $card['active_onboarding'])
                @can('crm.onboarding.create')
                    <form method="POST" action="{{ route('crm.customers.onboarding.start', $lead->crmCustomer) }}">@csrf<button class="text-violet-700 hover:text-violet-900 dark:text-violet-300">Start onboarding</button></form>
                @endcan
            @endif
        @elseif ($stage->value === 'won')
            @can('crm.customers.convert')
                <a href="{{ route('crm.customers.create-for-lead', $lead) }}" class="text-emerald-700 hover:text-emerald-900 dark:text-emerald-300">Convert</a>
            @endcan
        @endif
    </div>

    <details class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
        <summary class="cursor-pointer text-xs font-semibold text-slate-500 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white">Move stage</summary>
        <form method="POST" action="{{ route('crm.pipeline.cards.move', $lead) }}" class="mt-3 flex gap-2">
            @csrf
            <select name="target_stage" class="min-w-0 flex-1 text-xs">
                @foreach ($stages as $targetStage)
                    <option value="{{ $targetStage->value }}" @selected($targetStage === $stage)>{{ $targetStage->label() }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Move</button>
        </form>
    </details>
</article>
