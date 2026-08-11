@extends('layouts.admin')

@section('title', $batch->batch_number)
@section('page-title', $batch->batch_number)
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.barcode-batches.index') }}" class="hover:text-slate-950 dark:hover:text-white">Barcode Batches</a>
@endsection

@section('content')
@include('command-center.inventory.partials.nav')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <p class="text-sm text-slate-500">{{ $batch->template?->name }} / {{ $batch->total_labels }} scanner-ready labels</p>
        <button type="button" onclick="window.print()" class="min-h-11 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Print labels</button>
    </div>
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm print:border-0 print:p-0 print:shadow-none dark:border-slate-800 dark:bg-slate-900">
        <div class="label-sheet grid gap-3" style="grid-template-columns: repeat({{ max(1, (int) ($batch->template?->columns ?? 1)) }}, minmax(0, 1fr));">
            @foreach($batch->items as $item)
                @for($copy = 0; $copy < $item->quantity; $copy++)
                    <article class="label-card break-inside-avoid border border-dashed border-slate-300 p-3 text-center text-slate-950" style="min-height: {{ $batch->template?->label_height_mm ?? 25 }}mm;">
                        @if($batch->template?->show_product_name)<p class="truncate text-xs font-semibold">{{ $item->label_data['name'] }}</p>@endif
                        <div class="mx-auto mt-2 flex justify-center [&>svg]:max-w-full">{!! $barcodeSvgs[$item->id] !!}</div>
                        @if($batch->template?->show_barcode_text)<p class="mt-1 font-mono text-[10px]">{{ $item->label_data['barcode'] ?: $item->label_data['sku'] }}</p>@endif
                        @if($batch->template?->show_sku)<p class="mt-1 text-[10px]">SKU {{ $item->label_data['sku'] }}</p>@endif
                        @if($batch->template?->show_price)<p class="mt-1 text-xs font-bold">₹{{ number_format((float) $item->label_data['price'], 2) }}</p>@endif
                        @if($batch->template?->show_batch && filled($item->label_data['batch'] ?? null))<p class="mt-1 text-[10px]">Batch {{ $item->label_data['batch'] }}</p>@endif
                        @if($batch->template?->show_expiry && filled($item->label_data['expiry'] ?? null))<p class="text-[10px]">Exp {{ \Carbon\Carbon::parse($item->label_data['expiry'])->format('d M Y') }}</p>@endif
                    </article>
                @endfor
            @endforeach
        </div>
    </section>
</div>
<style>
@media print {
    @page { margin: {{ $batch->template?->margin_top_mm ?? 4 }}mm {{ $batch->template?->margin_right_mm ?? 4 }}mm {{ $batch->template?->margin_bottom_mm ?? 4 }}mm {{ $batch->template?->margin_left_mm ?? 4 }}mm; }
    body { background: white !important; }
    body > * { visibility: hidden; }
    .label-sheet, .label-sheet * { visibility: visible; }
    .label-sheet { position: absolute; inset: 0; gap: {{ $batch->template?->gap_vertical_mm ?? 1 }}mm {{ $batch->template?->gap_horizontal_mm ?? 1 }}mm; }
    .label-card { width: {{ $batch->template?->label_width_mm ?? 50 }}mm; height: {{ $batch->template?->label_height_mm ?? 25 }}mm; overflow: hidden; }
}
</style>
@endsection
