<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsTrustMetricRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['label' => ['required', 'string', 'max:255'], 'value' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string'], 'icon' => ['nullable', 'string', 'max:100'], 'show_on_homepage' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000']]; }
}
