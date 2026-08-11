<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn (array $item): bool => filled($item['product_id'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'items' => $items,
            'adjustment_type' => $this->input('adjustment_type', 'other'),
            'approval_required' => $this->boolean('approval_required', true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'adjustment_type' => ['required', 'in:opening_correction,damage,wastage,expiry,theft_loss,found_stock,system_correction,other'],
            'approval_required' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'items.*.adjusted_quantity' => ['required', 'numeric'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
