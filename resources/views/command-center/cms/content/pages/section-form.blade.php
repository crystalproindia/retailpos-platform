@php
    $sectionType = old('section_type', $section?->section_type ?? 'hero');
    $items = old('items', $section?->items ?? []);
    $items = count($items) ? $items : [[]];
    $value = fn (string $key, mixed $default = null) => old($key, data_get($section, $key, $default));
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <label class="block text-sm font-medium text-slate-700">Section name
        <input name="section_key" value="{{ $value('section_key') }}" required maxlength="100" class="cms-input mt-1" placeholder="for example: home_hero">
        <span class="mt-1 block text-xs font-normal text-slate-500">A short internal name to help your team find this section later.</span>
    </label>
    <label class="block text-sm font-medium text-slate-700">Content type
        <select name="section_type" class="cms-input mt-1" data-content-section-type>
            @foreach ($sectionTypes as $key => $label)<option value="{{ $key }}" @selected($sectionType === $key)>{{ $label }}</option>@endforeach
        </select>
        <span class="mt-1 block text-xs font-normal text-slate-500">Choose what this part of the page is meant to show.</span>
    </label>
    <label class="block text-sm font-medium text-slate-700">Small heading
        <input name="eyebrow" value="{{ $value('eyebrow') }}" maxlength="120" class="cms-input mt-1" placeholder="Optional label above the title">
        <span class="mt-1 block text-xs font-normal text-slate-500">Useful for a short category or announcement.</span>
    </label>
    <label class="block text-sm font-medium text-slate-700">Main title
        <input name="title" value="{{ $value('title') }}" maxlength="255" class="cms-input mt-1" placeholder="What should visitors notice first?">
        <span class="mt-1 block text-xs font-normal text-slate-500">Keep it clear, specific, and easy to scan.</span>
    </label>
    <label class="block text-sm font-medium text-slate-700 md:col-span-2">Supporting text
        <input name="subtitle" value="{{ $value('subtitle') }}" maxlength="500" class="cms-input mt-1" placeholder="Optional short explanation below the title">
        <span class="mt-1 block text-xs font-normal text-slate-500">Use one sentence to give the title useful context.</span>
    </label>
    <label class="block text-sm font-medium text-slate-700 md:col-span-2">Detailed content
        <textarea name="body" rows="4" class="cms-input mt-1" placeholder="Write the helpful detail visitors should see.">{{ $value('body') }}</textarea>
        <span class="mt-1 block text-xs font-normal text-slate-500">Plain text is supported. There is no code or JSON to manage here.</span>
    </label>
    <label class="block text-sm font-medium text-slate-700 md:col-span-2">Image link
        <input name="image_url" value="{{ $value('image_url') }}" inputmode="url" class="cms-input mt-1" placeholder="https://example.com/image.jpg">
        <span class="mt-1 block text-xs font-normal text-slate-500">Add a secure image link when this section needs an image. Media Library selection can be connected later.</span>
    </label>
</div>

<aside class="cms-preview mt-5" data-content-preview>
    <div class="cms-preview-header"><span>Section preview</span><span class="cms-chip">Updates as you type</span></div>
    <div class="p-5"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-teal-700" data-preview-eyebrow>{{ $value('eyebrow') ?: 'Optional small heading' }}</p><h3 class="mt-2 text-xl font-semibold text-slate-900" data-preview-title>{{ $value('title') ?: 'Your section title' }}</h3><p class="mt-2 text-sm leading-6 text-slate-600" data-preview-subtitle>{{ $value('subtitle') ?: 'Supporting text will appear here.' }}</p><span class="mt-4 inline-flex rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white" data-preview-button>{{ $value('primary_cta_label') ?: 'Primary button' }}</span></div>
</aside>

<div class="mt-5 border-t border-slate-200 pt-5">
    <h3 class="text-sm font-semibold text-slate-900">Buttons</h3>
    <p class="mt-1 text-sm text-slate-600">Optional calls to action that help visitors take the next step.</p>
    <div class="mt-3 grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">Primary button text<input name="primary_cta_label" value="{{ $value('primary_cta_label') }}" maxlength="120" class="cms-input mt-1" placeholder="Book a demo"></label>
        <label class="block text-sm font-medium text-slate-700">Primary button link<input name="primary_cta_url" value="{{ $value('primary_cta_url') }}" inputmode="url" class="cms-input mt-1" placeholder="/contact"></label>
        <label class="block text-sm font-medium text-slate-700">Second button text<input name="secondary_cta_label" value="{{ $value('secondary_cta_label') }}" maxlength="120" class="cms-input mt-1" placeholder="Learn more"></label>
        <label class="block text-sm font-medium text-slate-700">Second button link<input name="secondary_cta_url" value="{{ $value('secondary_cta_url') }}" inputmode="url" class="cms-input mt-1" placeholder="/pricing"></label>
    </div>
</div>

<div class="mt-5 border-t border-slate-200 pt-5" data-repeatable-items>
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h3 class="text-sm font-semibold text-slate-900">Section items</h3><p class="mt-1 text-sm text-slate-600">Add simple cards, questions, testimonials, or statistics for this section.</p></div><button type="button" data-add-item class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Add item</button></div>
    <div class="mt-4 space-y-3" data-items-list>
        @foreach ($items as $index => $item)
            @include('command-center.cms.content.pages.section-item', ['index' => $index, 'item' => $item])
        @endforeach
    </div>
    <template data-item-template>@include('command-center.cms.content.pages.section-item', ['index' => '__INDEX__', 'item' => []])</template>
</div>

<label class="mt-5 flex items-start gap-3 text-sm font-medium text-slate-700"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked((bool) $value('is_enabled', true)) class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-900"> <span>Show this section on the published page<span class="mt-1 block text-xs font-normal text-slate-500">Disabled sections are saved safely but do not appear in the public website API.</span></span></label>
