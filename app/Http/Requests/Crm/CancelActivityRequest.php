<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class CancelActivityRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('sales.followups.manage'); }

    /** @return array<string, mixed> */
    public function rules(): array { return ['outcome' => ['nullable', 'string', 'max:500']]; }
}
