@php
    $assessmentClass = match ($assessment->qualification) {
        'Hot Lead' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200',
        'Warm Lead' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
        'Cold Lead' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
@endphp
<span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $assessmentClass }}" title="Staff-entered conversation assessment">
    {{ $assessment->qualification }}@if ($assessment->average !== null) · {{ number_format($assessment->average, 1) }}/5 @endif
</span>
