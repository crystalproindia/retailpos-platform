<div class="flex min-w-0 items-center gap-3">
    <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-sm font-semibold text-slate-400 dark:bg-slate-800">
        @if ($product->imageUrl(true))
            <img src="{{ $product->imageUrl(true) }}" alt="" class="size-full object-cover" loading="lazy">
        @else
            {{ str($product->name)->substr(0, 1) }}
        @endif
    </div>
    <div class="min-w-0">
        <p class="truncate font-semibold">{{ $product->name }}</p>
        <p class="truncate text-xs text-slate-500">{{ $product->sku }}@if ($unit ?? null) · {{ $unit }}@endif</p>
    </div>
</div>
