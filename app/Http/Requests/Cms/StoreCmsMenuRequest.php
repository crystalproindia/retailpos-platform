<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCmsMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', Rule::in(array_keys(config('cms.menu_locations')))],
            'is_enabled' => ['required', 'boolean'],
        ];
    }
}
