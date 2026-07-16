@extends('layouts.admin')

@section('title', 'SEO Center')
@section('page-title', 'SEO Center')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>SEO</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <form method="POST" action="{{ route('cms.seo.update') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                @method('PUT')
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">SEO Defaults</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Default metadata and tracking integrations for the public website.</p>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <input name="default_meta_title" value="{{ old('default_meta_title', $seoSettings->default_meta_title) }}" placeholder="Default meta title" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="default_canonical_url" value="{{ old('default_canonical_url', $seoSettings->default_canonical_url) }}" placeholder="Default canonical URL" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <textarea name="default_meta_description" rows="3" placeholder="Default meta description" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('default_meta_description', $seoSettings->default_meta_description) }}</textarea>
                    <textarea name="default_meta_keywords" rows="2" placeholder="Default meta keywords" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('default_meta_keywords', $seoSettings->default_meta_keywords) }}</textarea>
                    <input type="number" name="default_og_image_id" value="{{ old('default_og_image_id', $seoSettings->default_og_image_id) }}" placeholder="Default OG image media ID" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input type="number" name="default_twitter_image_id" value="{{ old('default_twitter_image_id', $seoSettings->default_twitter_image_id) }}" placeholder="Default Twitter image media ID" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-800">
                        <input type="hidden" name="sitemap_enabled" value="0">
                        <input type="checkbox" name="sitemap_enabled" value="1" @checked(old('sitemap_enabled', $seoSettings->sitemap_enabled)) class="rounded border-slate-300">
                        Sitemap enabled
                    </label>
                    <textarea name="schema_markup" rows="4" placeholder="Schema.org markup foundation" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('schema_markup', $seoSettings->schema_markup) }}</textarea>
                    <textarea name="robots_txt" rows="5" placeholder="Robots.txt content" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('robots_txt', $seoSettings->robots_txt) }}</textarea>
                    <input name="sitemap_url" value="{{ old('sitemap_url', $seoSettings->sitemap_url) }}" placeholder="Public sitemap URL" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-800"><input type="hidden" name="robots_default_index" value="0"><input type="checkbox" name="robots_default_index" value="1" @checked(old('robots_default_index', $seoSettings->robots_default_index ?? true)) class="rounded border-slate-300">Index by default</label>
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2.5 text-sm dark:border-slate-800"><input type="hidden" name="robots_default_follow" value="0"><input type="checkbox" name="robots_default_follow" value="1" @checked(old('robots_default_follow', $seoSettings->robots_default_follow ?? true)) class="rounded border-slate-300">Follow links by default</label>
                </div>

                <h2 class="mt-6 text-base font-semibold text-slate-950 dark:text-white">Public business information</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <input name="company_name" value="{{ old('company_name', $seoSettings->company_name) }}" placeholder="Company name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="company_logo_url" value="{{ old('company_logo_url', $seoSettings->company_logo_url) }}" placeholder="Company logo URL" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="contact_phone_india" value="{{ old('contact_phone_india', $seoSettings->contact_phone_india) }}" placeholder="India phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="contact_phone_singapore" value="{{ old('contact_phone_singapore', $seoSettings->contact_phone_singapore) }}" placeholder="Singapore phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="contact_phone_malaysia" value="{{ old('contact_phone_malaysia', $seoSettings->contact_phone_malaysia) }}" placeholder="Malaysia phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="contact_email" value="{{ old('contact_email', $seoSettings->contact_email) }}" placeholder="Contact email" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <textarea name="address" rows="3" placeholder="Business address" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('address', $seoSettings->address) }}</textarea>
                    <textarea name="same_as_social_links" rows="3" placeholder='Social links JSON, for example ["https://linkedin.com/company/retailpos"]' class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('same_as_social_links', $seoSettings->same_as_social_links ? json_encode($seoSettings->same_as_social_links, JSON_PRETTY_PRINT) : '') }}</textarea>
                    <textarea name="default_schema_organization" rows="4" placeholder="Default organization schema JSON" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('default_schema_organization', $seoSettings->default_schema_organization ? json_encode($seoSettings->default_schema_organization, JSON_PRETTY_PRINT) : '') }}</textarea>
                </div>

                <h2 class="mt-6 text-base font-semibold text-slate-950 dark:text-white">Verification & Analytics</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <input name="search_console_verification" value="{{ old('search_console_verification', $seoSettings->search_console_verification) }}" placeholder="Search Console verification" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="google_analytics_id" value="{{ old('google_analytics_id', $seoSettings->google_analytics_id) }}" placeholder="Google Analytics ID" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="google_tag_manager_id" value="{{ old('google_tag_manager_id', $seoSettings->google_tag_manager_id) }}" placeholder="Google Tag Manager ID" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="facebook_pixel_id" value="{{ old('facebook_pixel_id', $seoSettings->facebook_pixel_id) }}" placeholder="Facebook Pixel ID" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="linkedin_insight_tag" value="{{ old('linkedin_insight_tag', $seoSettings->linkedin_insight_tag) }}" placeholder="LinkedIn Insight Tag" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="microsoft_clarity_id" value="{{ old('microsoft_clarity_id', $seoSettings->microsoft_clarity_id) }}" placeholder="Microsoft Clarity ID" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>

                <div class="mt-6 flex justify-end">
                    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save SEO</button>
                </div>
            </form>

            <div class="space-y-6">
                <form method="POST" action="{{ route('cms.seo.redirects.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Redirect Manager</h2>
                    <div class="mt-4 space-y-3">
                        <input name="source_url" placeholder="/old-url" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="target_url" placeholder="/new-url" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <select name="status_code" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach ([301, 302, 307, 308] as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="is_enabled" value="1">
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Create redirect</button>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Redirects</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($redirects as $redirect)
                            <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                <p class="font-medium text-slate-950 dark:text-white">{{ $redirect->source_url }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $redirect->status_code }} to {{ $redirect->target_url }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">No redirects configured.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Broken Link Monitor</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($brokenLinks as $link)
                            <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                                <p class="font-medium text-slate-950 dark:text-white">{{ $link->broken_url }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $link->source_url }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 dark:text-slate-400">Monitor foundation is ready; crawler integration can populate this table later.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </section>
    </div>
@endsection
