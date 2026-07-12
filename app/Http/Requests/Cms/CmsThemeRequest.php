<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsThemeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['primary_color' => ['nullable', 'string', 'max:20'], 'secondary_color' => ['nullable', 'string', 'max:20'], 'accent_color' => ['nullable', 'string', 'max:20'], 'background_color' => ['nullable', 'string', 'max:20'], 'text_color' => ['nullable', 'string', 'max:20'], 'button_color' => ['nullable', 'string', 'max:20'], 'button_radius_style' => ['nullable', Rule::in(['square', 'rounded', 'pill'])], 'card_radius_style' => ['nullable', Rule::in(['square', 'rounded', 'soft'])], 'website_theme_mode' => ['required', Rule::in(['clean_light', 'premium_dark', 'soft_saas'])], 'header_style' => ['nullable', 'string', 'max:100'], 'footer_style' => ['nullable', 'string', 'max:100'], 'cta_button_style' => ['nullable', 'string', 'max:100']]; }
}
