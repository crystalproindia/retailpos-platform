@props(['points' => []])
@php
    $rows = collect($points)->values();
    $maximum = max(1, (int) $rows->flatMap(fn (array $row) => [abs((int) $row['sales']), abs((int) $row['purchases'])])->max());
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if($rows->isEmpty())
        <div class="grid h-56 place-items-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950/50">No sales or purchase activity in this period.</div>
    @else
        <div class="flex h-56 items-end gap-2 overflow-hidden sm:gap-3">
            @foreach($rows->take(18) as $point)
                <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <div class="flex h-44 w-full items-end justify-center gap-1" title="Sales {{ number_format($point['sales'] / 100, 2) }}; Purchases {{ number_format($point['purchases'] / 100, 2) }}">
                        <span class="w-2/5 rounded-t-sm bg-sky-500" style="height: {{ max(2, (abs($point['sales']) / $maximum) * 100) }}%"></span>
                        <span class="w-2/5 rounded-t-sm bg-amber-400 dark:bg-amber-300" style="height: {{ max(2, (abs($point['purchases']) / $maximum) * 100) }}%"></span>
                    </div>
                    <span class="max-w-full truncate text-[0.65rem] text-slate-500">{{ $point['label'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-xs font-medium text-slate-500 dark:text-slate-400"><span class="inline-flex items-center gap-2"><i class="size-2 rounded-sm bg-sky-500"></i>Net sales</span><span class="inline-flex items-center gap-2"><i class="size-2 rounded-sm bg-amber-400"></i>Net purchases</span></div>
    @endif
</div>
