<?php

namespace App\Models\Ai;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'forecast_type', 'algorithm_version', 'parameters', 'training_start', 'training_end', 'forecast_start', 'forecast_end', 'status', 'data_points', 'confidence_level', 'safe_error_message', 'started_at', 'completed_at', 'created_by'])]
class AiForecastRun extends Model
{
    protected function casts(): array { return ['parameters' => 'array', 'training_start' => 'date', 'training_end' => 'date', 'forecast_start' => 'date', 'forecast_end' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function results(): HasMany { return $this->hasMany(AiForecastResult::class, 'forecast_run_id'); }
}
