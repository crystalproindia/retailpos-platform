<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesChannelRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['store', 'website', 'marketplace', 'social', 'other'])],
            'description' => ['nullable', 'string'],
            'is_online' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sync_enabled' => ['nullable', 'boolean'],
            'price_strategy' => ['required', 'string', 'max:80'],
            'stock_strategy' => ['required', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
