<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsFaqRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['question' => ['required', 'string', 'max:255'], 'answer' => ['required', 'string'], 'category' => ['nullable', 'string', 'max:255'], 'page_location' => ['nullable', 'string', 'max:255'], 'is_active' => ['nullable', 'boolean'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000']]; }
}
