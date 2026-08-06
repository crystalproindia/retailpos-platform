<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StorePosReturnRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('pos.returns.create') ?? false; }

    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'integer'], 'return_type' => ['required', 'in:full_return,partial_return,exchange'],
            'reason_code' => ['nullable', 'string', 'max:100'], 'reason_text' => ['nullable', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:2000'],
            'window_override' => ['nullable', 'boolean'], 'override_reason' => ['nullable', 'required_if:window_override,1', 'string', 'max:1000'], 'receipt_confirmed' => ['nullable', 'boolean'], 'exchange_sale_id' => ['nullable', 'integer'], 'idempotency_key' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'], 'items.*.original_sale_item_id' => ['required', 'integer'], 'items.*.return_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.stock_disposition' => ['required', 'in:restock,damaged,scrap,quarantine,no_stock_change'], 'items.*.condition_note' => ['nullable', 'string', 'max:1000'],
            'refunds' => ['required', 'array', 'min:1'], 'refunds.*.original_payment_id' => ['nullable', 'integer'], 'refunds.*.method' => ['required', 'in:cash,card,upi,bank_transfer,store_credit,other'],
            'refunds.*.amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'refunds.*.external_reference' => ['nullable', 'string', 'max:150'],
        ];
    }
}
