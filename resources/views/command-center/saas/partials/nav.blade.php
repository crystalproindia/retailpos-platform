@php
    $saasNavigation = app(\App\Support\Navigation\SaasNavigationRegistry::class);
@endphp

<nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-4 text-sm dark:border-slate-800">
    @foreach ($saasNavigation->platformItems(auth()->user()) as $item)
        <a href="{{ $saasNavigation->url($item) }}" class="rounded-md px-3 py-2 {{ $saasNavigation->isActive($item) ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">{{ $item['label'] }}</a>
    @endforeach
</nav>
