<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosOfflineSyncRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['batch_uuid' => ['required', 'uuid'], 'device_id' => ['nullable', 'string', 'max:100'], 'records' => ['required', 'array', 'min:1', 'max:50'], 'records.*.offline_uuid' => ['required', 'uuid'], 'records.*.offline_reference' => ['required', 'string', 'max:100'], 'records.*.offline_created_at' => ['nullable', 'date'], 'records.*.customer' => ['nullable', 'array'], 'records.*.customer.mobile' => ['nullable', 'string', 'max:50'], 'records.*.customer.name' => ['nullable', 'string', 'max:150'], 'records.*.items' => ['required', 'array', 'min:1'], 'records.*.items.*.product_id' => ['required', 'integer'], 'records.*.items.*.quantity' => ['required', 'numeric', 'gt:0'], 'records.*.items.*.unit_price' => ['nullable', 'numeric', 'min:0'], 'records.*.payments' => ['required', 'array', 'min:1'], 'records.*.payments.*.method' => ['required', 'in:cash,card,upi,bank_transfer,other'], 'records.*.payments.*.amount' => ['required', 'numeric', 'gt:0'], 'records.*.payments.*.reference' => ['nullable', 'string', 'max:100'], 'records.*.notes' => ['nullable', 'string', 'max:1000'], 'records.*.coupon_code' => ['nullable', 'string', 'max:80']];
    }
}
