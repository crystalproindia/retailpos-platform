<?php

namespace App\Models\Tasks;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'rule_key', 'is_enabled', 'threshold_hours', 'configuration', 'updated_by'])]
class TaskRuleSetting extends Model
{
    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'configuration' => 'array'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
