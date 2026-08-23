@props([
    'src' => null,
    'name' => 'Product',
    'size' => 'size-10',
])

<span {{ $attributes->class([$size, 'grid shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500']) }}>
    @if ($src)
        <img src="{{ $src }}" alt="" class="size-full object-contain p-1" loading="lazy" decoding="async">
    @else
        <span aria-hidden="true">{{ str($name ?: 'P')->substr(0, 1)->upper() }}</span>
    @endif
</span>
