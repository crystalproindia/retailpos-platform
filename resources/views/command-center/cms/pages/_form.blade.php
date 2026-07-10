@php
    $seo = $page->seo;
@endphp

@csrf
@if (($method ?? 'POST') !== 'POST')
    @method($method)
@endif

<div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
    <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Title</label>
            <input id="title" name="title" value="{{ old('title', $page->title) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
            @error('title') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="slug" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Slug</label>
                <input id="slug" name="slug" value="{{ old('slug', $page->slug) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                @error('slug') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="subtitle" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Subtitle</label>
                <input id="subtitle" name="subtitle" value="{{ old('subtitle', $page->subtitle) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
            </div>
        </div>

        <div>
            <label for="hero_content" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Hero Content</label>
            <textarea id="hero_content" name="hero_content" rows="4" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">{{ old('hero_content', $page->hero_content) }}</textarea>
        </div>

        <div>
            <label for="body_content" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Body Content</label>
            <textarea id="body_content" name="body_content" rows="10" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">{{ old('body_content', $page->body_content) }}</textarea>
        </div>
    </section>

    <aside class="space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Publishing</h2>
            <div class="mt-5 space-y-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                    <select id="status" name="status" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                        @foreach (['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $page->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="scheduled_for" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Scheduled For</label>
                    <input id="scheduled_for" type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for', $page->scheduled_for?->format('Y-m-d\TH:i')) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                </div>
                <div>
                    <label for="featured_image_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Featured Image ID</label>
                    <input id="featured_image_id" type="number" name="featured_image_id" value="{{ old('featured_image_id', $page->featured_image_id) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">SEO</h2>
            <div class="mt-5 space-y-4">
                <input name="meta_title" value="{{ old('meta_title', $seo?->meta_title) }}" placeholder="Meta title" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <textarea name="meta_description" rows="3" placeholder="Meta description" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('meta_description', $seo?->meta_description) }}</textarea>
                <textarea name="meta_keywords" rows="2" placeholder="Meta keywords" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('meta_keywords', $seo?->meta_keywords) }}</textarea>
                <input name="canonical_url" value="{{ old('canonical_url', $seo?->canonical_url) }}" placeholder="Canonical URL" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <input name="og_title" value="{{ old('og_title', $seo?->og_title) }}" placeholder="Open Graph title" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <textarea name="og_description" rows="2" placeholder="Open Graph description" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('og_description', $seo?->og_description) }}</textarea>
                <input name="og_type" value="{{ old('og_type', $seo?->og_type) }}" placeholder="Open Graph type" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <input name="twitter_title" value="{{ old('twitter_title', $seo?->twitter_title) }}" placeholder="Twitter title" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <textarea name="twitter_description" rows="2" placeholder="Twitter description" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('twitter_description', $seo?->twitter_description) }}</textarea>
                <input name="twitter_card" value="{{ old('twitter_card', $seo?->twitter_card ?? 'summary_large_image') }}" placeholder="Twitter card" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
        </section>
    </aside>
</div>

<div class="mt-6 flex justify-end">
    <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">
        Save page
    </button>
</div>
