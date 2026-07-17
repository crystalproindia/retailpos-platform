@php
    $itemValue = fn (string $key) => data_get($item, $key);
@endphp
<article class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-content-item>
    <div class="mb-3 flex items-center justify-between"><p class="text-sm font-semibold text-slate-800">Content item</p><button type="button" data-remove-item class="text-sm font-semibold text-rose-700">Remove</button></div>
    <div class="grid gap-3 md:grid-cols-2" data-item-fields="standard">
        <label class="block text-sm font-medium text-slate-700">Title<input name="items[{{ $index }}][title]" value="{{ $itemValue('title') }}" maxlength="255" class="cms-input mt-1" placeholder="Feature or card title"></label>
        <label class="block text-sm font-medium text-slate-700">Icon name<input name="items[{{ $index }}][icon_key]" value="{{ $itemValue('icon_key') }}" maxlength="80" class="cms-input mt-1" placeholder="Optional icon name"></label>
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">Description<textarea name="items[{{ $index }}][description]" rows="2" class="cms-input mt-1" placeholder="Short, useful explanation">{{ $itemValue('description') }}</textarea></label>
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">Link<input name="items[{{ $index }}][url]" value="{{ $itemValue('url') }}" inputmode="url" class="cms-input mt-1" placeholder="Optional secure link"></label>
    </div>
    <div class="hidden grid gap-3 md:grid-cols-2" data-item-fields="faq">
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">Question<input name="items[{{ $index }}][question]" value="{{ $itemValue('question') }}" maxlength="255" class="cms-input mt-1" placeholder="What would a visitor ask?"></label>
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">Answer<textarea name="items[{{ $index }}][answer]" rows="3" class="cms-input mt-1" placeholder="Give a short, clear answer">{{ $itemValue('answer') }}</textarea></label>
    </div>
    <div class="hidden grid gap-3 md:grid-cols-2" data-item-fields="testimonials">
        <label class="block text-sm font-medium text-slate-700">Customer name<input name="items[{{ $index }}][name]" value="{{ $itemValue('name') }}" maxlength="160" class="cms-input mt-1" placeholder="Customer name"></label>
        <label class="block text-sm font-medium text-slate-700">Role or company<input name="items[{{ $index }}][role_company]" value="{{ $itemValue('role_company') }}" maxlength="255" class="cms-input mt-1" placeholder="Owner, Northwind Retail"></label>
        <label class="block text-sm font-medium text-slate-700">Rating (1 to 5)<input name="items[{{ $index }}][rating]" value="{{ $itemValue('rating') }}" type="number" min="1" max="5" class="cms-input mt-1" placeholder="5"></label>
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">Customer quote<textarea name="items[{{ $index }}][quote]" rows="3" class="cms-input mt-1" placeholder="A short customer story">{{ $itemValue('quote') }}</textarea></label>
    </div>
    <div class="hidden grid gap-3 md:grid-cols-2" data-item-fields="stats">
        <label class="block text-sm font-medium text-slate-700">Label<input name="items[{{ $index }}][label]" value="{{ $itemValue('label') }}" maxlength="120" class="cms-input mt-1" placeholder="Stores supported"></label>
        <label class="block text-sm font-medium text-slate-700">Value<input name="items[{{ $index }}][value]" value="{{ $itemValue('value') }}" maxlength="120" class="cms-input mt-1" placeholder="500+"></label>
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">Short explanation<textarea name="items[{{ $index }}][description]" rows="2" class="cms-input mt-1" placeholder="Optional context for this number">{{ $itemValue('description') }}</textarea></label>
    </div>
</article>
