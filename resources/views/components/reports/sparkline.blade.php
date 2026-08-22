@props(['values' => [], 'tone' => 'teal'])
@php
    $points = collect($values)->map(fn ($value) => (float) $value)->values();
    $min = min(0, (float) ($points->min() ?? 0));
    $max = max(1, (float) ($points->max() ?? 0));
    $range = max(1, $max - $min);
    $polyline = $points->map(function (float $value, int $index) use ($points, $min, $range) {
        $x = $points->count() > 1 ? ($index / ($points->count() - 1)) * 96 : 48;
        $y = 28 - (($value - $min) / $range) * 24;
        return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
    })->implode(' ');
    $stroke = match ($tone) {
        'emerald' => 'rgb(16 185 129)',
        'indigo' => 'rgb(99 102 241)',
        'sky' => 'rgb(14 165 233)',
        default => 'rgb(13 148 136)',
    };
@endphp
<svg {{ $attributes->merge(['class' => 'h-8 w-24 overflow-visible']) }} viewBox="0 0 96 32" role="img" aria-label="Recent trend">
    @if($points->count() > 1)
        <polyline points="{{ $polyline }}" fill="none" stroke="{{ $stroke }}" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
    @else
        <path d="M2 24 H94" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 4" class="text-slate-300 dark:text-slate-700" />
    @endif
</svg>
