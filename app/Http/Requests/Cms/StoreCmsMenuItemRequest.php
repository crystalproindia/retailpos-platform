<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreCmsMenuItemRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:cms_menu_items,id'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'opens_new_tab' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
