<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class PosQuickCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['mobile' => ['required', 'string', 'min:6', 'max:50'], 'name' => ['nullable', 'string', 'max:255']];
    }
}
