@props(['points' => [], 'primaryKey' => 'net_sales', 'secondaryKey' => 'gross_profit', 'primaryLabel' => 'Net sales', 'secondaryLabel' => 'Gross profit'])
@php
    $rows = collect($points)->values();
    $values = $rows->flatMap(fn (array $row) => [(float) ($row[$primaryKey] ?? 0), (float) ($row[$secondaryKey] ?? 0)]);
    $min = min(0, (float) ($values->min() ?? 0));
    $max = max(1, (float) ($values->max() ?? 0));
    $range = max(1, $max - $min);
    $coordinates = function (string $key) use ($rows, $min, $range): string {
        return $rows->map(function (array $row, int $index) use ($key, $rows, $min, $range): string {
            $x = $rows->count() > 1 ? 48 + ($index / ($rows->count() - 1)) * 704 : 400;
            $y = 224 - ((((float) ($row[$key] ?? 0)) - $min) / $range) * 176;
            return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        })->implode(' ');
    };
    $labelIndexes = $rows->isEmpty() ? collect() : collect([0, intdiv(max(0, $rows->count() - 1), 2), max(0, $rows->count() - 1)])->unique();
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if($rows->isEmpty())
        <div class="grid h-64 place-items-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-center dark:border-slate-700 dark:bg-slate-950/50">
            <div><x-icon name="activity" class="mx-auto size-6 text-slate-400" /><p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">No activity in this period</p><p class="mt-1 text-xs text-slate-500">Chart data will appear after authorized sales are recorded.</p></div>
        </div>
    @elseif($rows->count() === 1)
        @php($point = $rows->first())
        <div class="min-h-56 rounded-lg border border-slate-200 bg-slate-50/70 p-5 dark:border-slate-700 dark:bg-slate-950/50 sm:p-6">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
                <div><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Single-period result</p><p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $point['label'] }}</p></div>
                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">1 data point</span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach([[$primaryKey, $primaryLabel, 'bg-teal-600', 'text-teal-700 dark:text-teal-300'], [$secondaryKey, $secondaryLabel, 'bg-indigo-500', 'text-indigo-700 dark:text-indigo-300']] as [$key, $label, $dot, $text])
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400"><span class="size-2.5 rounded-full {{ $dot }}"></span>{{ $label }}</div><p class="mt-3 text-xl font-semibold {{ $text }}">INR {{ number_format(((int) ($point[$key] ?? 0)) / 100, 2) }}</p></div>
                @endforeach
            </div>
        </div>
    @else
        <div class="overflow-hidden">
            <svg viewBox="0 0 800 260" class="h-auto min-h-56 w-full" role="img" aria-label="{{ $primaryLabel }} compared with {{ $secondaryLabel }}">
                @foreach([48, 92, 136, 180, 224] as $gridY)
                    <line x1="48" y1="{{ $gridY }}" x2="752" y2="{{ $gridY }}" stroke="currentColor" stroke-width="1" class="text-slate-200 dark:text-slate-800" />
                @endforeach
                <polyline points="{{ $coordinates($primaryKey) }}" fill="none" stroke="rgb(13 148 136)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                <polyline points="{{ $coordinates($secondaryKey) }}" fill="none" stroke="rgb(99 102 241)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                @foreach($labelIndexes as $index)
                    @php($x = $rows->count() > 1 ? 48 + ($index / ($rows->count() - 1)) * 704 : 400)
                    <text x="{{ $x }}" y="252" text-anchor="{{ $index === 0 ? 'start' : ($index === $rows->count() - 1 ? 'end' : 'middle') }}" fill="currentColor" class="text-[12px] text-slate-500">{{ $rows[$index]['label'] }}</text>
                @endforeach
            </svg>
        </div>
        <div class="mt-2 flex flex-wrap gap-4 text-xs font-medium text-slate-500 dark:text-slate-400"><span class="inline-flex items-center gap-2"><i class="size-2 rounded-full bg-teal-600"></i>{{ $primaryLabel }}</span><span class="inline-flex items-center gap-2"><i class="size-2 rounded-full bg-indigo-500"></i>{{ $secondaryLabel }}</span></div>
    @endif
</div>
