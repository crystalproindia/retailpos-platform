<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePosReturnSettingsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('pos.returns.settings.manage') ?? false; }
    public function rules(): array
    {
        return ['return_window_days' => ['required', 'integer', 'min:0', 'max:365'], 'approval_threshold' => ['nullable', 'regex:/^\d+(?:\.\d{1,2})?$/'], 'receipt_required' => ['nullable', 'boolean'], 'manager_approval_required' => ['nullable', 'boolean'], 'cashiers_may_initiate' => ['nullable', 'boolean'], 'refund_original_method_only' => ['nullable', 'boolean'], 'store_credit_allowed' => ['nullable', 'boolean'], 'damaged_may_restock' => ['nullable', 'boolean'], 'anonymous_returns_allowed' => ['nullable', 'boolean']];
    }
}
