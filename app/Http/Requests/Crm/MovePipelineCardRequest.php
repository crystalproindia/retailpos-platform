<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovePipelineCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('crm.pipeline.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_stage' => ['required', Rule::enum(PipelineStage::class)],
        ];
    }
}
