@php
    $links = [
        ['label' => 'Overview', 'route' => 'cms.dashboard'],
        ['label' => 'Pages', 'route' => 'cms.pages.index'],
        ['label' => 'Homepage', 'route' => 'cms.homepage.index'],
        ['label' => 'Menus', 'route' => 'cms.menus.index'],
        ['label' => 'Media', 'route' => 'cms.media.index'],
        ['label' => 'Settings', 'route' => 'cms.settings.index'],
        ['label' => 'SEO', 'route' => 'cms.seo.index'],
    ];
@endphp

<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <nav class="flex min-w-max gap-1">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
                class="rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($link['route']) || str(request()->route()->getName())->startsWith(str($link['route'])->beforeLast('.')->toString()) ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</div>
