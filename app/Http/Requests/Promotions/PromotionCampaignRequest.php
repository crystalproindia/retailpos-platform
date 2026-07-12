<?php

namespace App\Http\Requests\Promotions;

use App\Enums\Promotions\CampaignType;
use App\Enums\Promotions\PromotionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionCampaignRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'campaign_type' => ['required', Rule::in(array_column(CampaignType::cases(), 'value'))], 'status' => ['required', Rule::in(array_column(PromotionStatus::cases(), 'value'))], 'priority' => ['required', 'integer', 'min:0', 'max:10000'], 'start_at' => ['nullable', 'date'], 'end_at' => ['nullable', 'date', 'after_or_equal:start_at']];
    }
}
