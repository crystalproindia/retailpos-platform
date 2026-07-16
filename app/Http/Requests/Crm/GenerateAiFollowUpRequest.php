<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\FollowUpMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAiFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.ai.generate');
    }

    public function rules(): array
    {
        return [
            'message_type' => ['required', Rule::enum(FollowUpMessageType::class)],
            'tone' => ['required', Rule::in(['professional', 'friendly', 'short', 'premium', 'tamil_english'])],
            'length' => ['required', Rule::in(['short', 'normal', 'detailed'])],
        ];
    }
}
