<section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div>
        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Page Sections</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Structured content blocks for the future public website.</p>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($page->sections as $section)
            <details class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <summary class="cursor-pointer font-medium text-slate-950 dark:text-white">{{ $section->section_key }} <span class="ml-2 text-sm font-normal text-slate-500">{{ str($section->section_type)->replace('_', ' ')->headline() }}</span></summary>
                <form method="POST" action="{{ route('cms.pages.sections.update', [$page, $section]) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <input name="section_key" value="{{ $section->section_key }}" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <select name="section_type" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach (['hero', 'feature_grid', 'trust_metrics', 'client_logos', 'faq', 'cta', 'custom'] as $type)
                            <option value="{{ $type }}" @selected($section->section_type === $type)>{{ str($type)->replace('_', ' ')->headline() }}</option>
                        @endforeach
                    </select>
                    <input name="title" value="{{ $section->title }}" placeholder="Title" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="subtitle" value="{{ $section->subtitle }}" placeholder="Subtitle" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <textarea name="content" rows="3" placeholder="Content" class="md:col-span-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $section->content }}</textarea>
                    <textarea name="settings" rows="2" placeholder='Settings JSON, e.g. {"columns": 3}' class="md:col-span-2 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $section->settings ? json_encode($section->settings, JSON_UNESCAPED_SLASHES) : '' }}</textarea>
                    <input type="number" name="sort_order" value="{{ $section->sort_order }}" min="0" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($section->is_active) class="rounded border-slate-300"> Active</label>
                    <div class="flex gap-3 md:col-span-2">
                        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save section</button>
                        <button formmethod="POST" formaction="{{ route('cms.pages.sections.destroy', [$page, $section]) }}" name="_method" value="DELETE" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 dark:border-rose-800 dark:text-rose-300">Remove</button>
                    </div>
                </form>
            </details>
        @empty
            <p class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No structured sections have been added to this page.</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('cms.pages.sections.store', $page) }}" class="mt-5 grid gap-3 rounded-lg border border-dashed border-slate-300 p-4 md:grid-cols-2 dark:border-slate-700">
        @csrf
        <p class="md:col-span-2 font-semibold text-slate-950 dark:text-white">Add section</p>
        <input name="section_key" placeholder="Section key, e.g. conversion-cta" required class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        <select name="section_type" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            @foreach (['hero', 'feature_grid', 'trust_metrics', 'client_logos', 'faq', 'cta', 'custom'] as $type)
                <option value="{{ $type }}">{{ str($type)->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>
        <input name="title" placeholder="Title" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        <input name="subtitle" placeholder="Subtitle" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        <textarea name="content" rows="3" placeholder="Content" class="md:col-span-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
        <textarea name="settings" rows="2" placeholder='Settings JSON, e.g. {"columns": 3}' class="md:col-span-2 rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
        <input type="number" name="sort_order" value="0" min="0" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"> Active</label>
        <div class="md:col-span-2"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Add section</button></div>
    </form>
</section>
