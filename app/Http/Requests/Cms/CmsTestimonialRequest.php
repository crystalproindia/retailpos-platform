<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsTestimonialRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['client_name' => ['required', 'string', 'max:255'], 'company_name' => ['nullable', 'string', 'max:255'], 'designation' => ['nullable', 'string', 'max:255'], 'logo_or_photo_media_id' => ['nullable', 'integer', 'exists:cms_media,id'], 'case_study_id' => ['nullable', 'integer', 'exists:cms_case_studies,id'], 'testimonial_text' => ['required', 'string'], 'rating' => ['nullable', 'integer', 'min:1', 'max:5'], 'industry' => ['nullable', 'string', 'max:255'], 'is_featured' => ['nullable', 'boolean'], 'show_on_homepage' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000']]; }
}
