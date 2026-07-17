<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsPageSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (is_string($settings) && trim($settings) !== '') {
            $decoded = json_decode($settings, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['settings' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'section_type' => ['required', Rule::in(['hero', 'feature_grid', 'trust_metrics', 'client_logos', 'case_study_grid', 'product_grid', 'module_grid', 'industry_grid', 'solution_grid', 'faq', 'testimonial', 'pricing', 'cta', 'rich_text', 'stats', 'image_text', 'custom_json'])],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
