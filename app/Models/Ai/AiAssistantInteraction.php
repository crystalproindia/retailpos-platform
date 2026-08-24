<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'user_id', 'outlet_id', 'conversation_id', 'intent', 'provider', 'model', 'status', 'prompt_digest', 'context_fact_count', 'input_tokens', 'output_tokens', 'duration_ms', 'safe_error_code', 'date_scope'])]
class AiAssistantInteraction extends Model
{
    protected function casts(): array
    {
        return ['date_scope' => 'array'];
    }
}
