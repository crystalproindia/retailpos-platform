@php
    $links = [
        ['label' => 'Control Center', 'route' => 'cms.dashboard'],
        ['label' => 'Branding', 'route' => 'cms.branding.index'],
        ['label' => 'Theme', 'route' => 'cms.theme.index'],
        ['label' => 'Header', 'route' => 'cms.header.index'],
        ['label' => 'Homepage', 'route' => 'cms.homepage.index'],
        ['label' => 'Pages', 'route' => 'cms.pages.index'],
        ['label' => 'Case Studies', 'route' => 'cms.case-studies.index'],
        ['label' => 'Client Logos', 'route' => 'cms.client-logos.index'],
        ['label' => 'Testimonials', 'route' => 'cms.testimonials.index'],
        ['label' => 'Trust', 'route' => 'cms.trust-metrics.index'],
        ['label' => 'FAQs', 'route' => 'cms.faqs.index'],
        ['label' => 'CTAs', 'route' => 'cms.ctas.index'],
        ['label' => 'Media', 'route' => 'cms.media.index'],
        ['label' => 'SEO', 'route' => 'cms.seo.index'],
        ['label' => 'Footer', 'route' => 'cms.footer.index'],
        ['label' => 'Settings', 'route' => 'cms.settings.index'],
    ];
@endphp

<div class="cms-tabs overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <nav class="flex min-w-max gap-1.5 pr-3">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
                class="whitespace-nowrap rounded-md px-3.5 py-2.5 text-sm font-medium transition {{ request()->routeIs($link['route']) || str(request()->route()->getName())->startsWith(str($link['route'])->beforeLast('.')->toString()) ? 'bg-slate-950 text-white shadow-sm dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</div>
