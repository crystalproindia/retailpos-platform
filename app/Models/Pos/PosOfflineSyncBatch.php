<?php

namespace App\Models\Pos;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'branch_id', 'user_id', 'batch_uuid', 'device_id', 'status', 'total_records', 'synced_records', 'failed_records', 'started_at', 'completed_at', 'metadata'])]
class PosOfflineSyncBatch extends Model
{
    protected function casts(): array { return ['metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function records(): HasMany { return $this->hasMany(PosOfflineSyncRecord::class, 'sync_batch_id'); }
}
