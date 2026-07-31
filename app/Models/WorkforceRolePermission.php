<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workforce_role_id', 'permission_key'])]
class WorkforceRolePermission extends Model
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(WorkforceRole::class, 'workforce_role_id');
    }
}
