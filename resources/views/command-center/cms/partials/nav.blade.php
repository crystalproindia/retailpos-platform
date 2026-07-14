@php
    $links = [
        ['label' => 'Control Center', 'route' => 'cms.dashboard'],
        ['label' => 'Website Settings', 'route' => 'website.settings.index'],
        ['label' => 'Theme', 'route' => 'cms.theme.index'],
        ['label' => 'Navigation', 'route' => 'website.navigation.index'],
        ['label' => 'Homepage', 'route' => 'cms.homepage.index'],
        ['label' => 'Pages', 'route' => 'cms.pages.index'],
        ['label' => 'Case Studies', 'route' => 'cms.case-studies.index'],
        ['label' => 'Client Logos (Coming Soon)', 'route' => 'website.settings.index'],
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

<div class="cms-tabs overflow-x-auto rounded-xl border border-slate-200/90 bg-white/95 p-2 shadow-[0_8px_24px_rgb(15_23_42_/_0.045)]">
    <nav class="flex min-w-max gap-2 pr-3" aria-label="CMS sections">
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
                class="whitespace-nowrap rounded-lg px-4 py-2.5 text-sm font-semibold transition {{ request()->routeIs($link['route']) || str(request()->route()->getName())->startsWith(str($link['route'])->beforeLast('.')->toString()) ? 'bg-teal-600 text-white shadow-sm shadow-teal-600/20' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                {{ $link['label'] }}
            </a>
        @endforeach
    </nav>
</div>
