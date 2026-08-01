<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'outlet_id', 'employee_id', 'weekday', 'is_active', 'notes', 'created_by'])]
class WeeklyOff extends Model
{
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
