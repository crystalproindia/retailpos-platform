<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsCaseStudyRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['title' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255'], 'client_name' => ['required', 'string', 'max:255'], 'client_logo_media_id' => ['nullable', 'integer', 'exists:cms_media,id'], 'featured_image_media_id' => ['nullable', 'integer', 'exists:cms_media,id'], 'og_image_media_id' => ['nullable', 'integer', 'exists:cms_media,id'], 'industry' => ['nullable', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'], 'project_type' => ['nullable', 'string', 'max:255'], 'short_summary' => ['nullable', 'string'], 'challenge' => ['nullable', 'string'], 'solution' => ['nullable', 'string'], 'key_features' => ['nullable', 'string'], 'results' => ['nullable', 'string'], 'testimonial_quote' => ['nullable', 'string'], 'related_product' => ['nullable', 'string', 'max:255'], 'related_module' => ['nullable', 'string', 'max:255'], 'related_industry' => ['nullable', 'string', 'max:255'], 'cta_text' => ['nullable', 'string', 'max:255'], 'cta_link' => ['nullable', 'string', 'max:255'], 'status' => ['required', Rule::in(['draft', 'published', 'scheduled'])], 'is_featured' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'], 'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string'], 'metrics' => ['nullable', 'array'], 'gallery_media_ids' => ['nullable', 'array'], 'gallery_media_ids.*' => ['nullable', 'integer', 'exists:cms_media,id'], 'sections' => ['nullable', 'array'], 'sections.*.section_type' => ['nullable', 'string', 'max:100'], 'sections.*.title' => ['nullable', 'string', 'max:255'], 'sections.*.subtitle' => ['nullable', 'string', 'max:255'], 'sections.*.content' => ['nullable', 'string'], 'sections.*.media_id' => ['nullable', 'integer', 'exists:cms_media,id'], 'sections.*.sort_order' => ['nullable', 'integer', 'min:0']]; }
}
