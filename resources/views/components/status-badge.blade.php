@props(['status'])
@php($tone = match($status) { 'published', 'active' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200', 'scheduled' => 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200', 'draft' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200', default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' })
<span {{ $attributes->merge(['class' => 'rounded-full px-2.5 py-1 text-xs font-semibold '.$tone]) }}>{{ str($status)->headline() }}</span>
