<?php

namespace App\Models\Pos;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'sync_batch_id', 'user_id', 'offline_uuid', 'device_id', 'record_type', 'payload', 'status', 'server_reference_type', 'server_reference_id', 'error_message', 'warning_message', 'attempted_at', 'synced_at', 'metadata'])]
class PosOfflineSyncRecord extends Model
{
    protected function casts(): array { return ['payload' => 'array', 'metadata' => 'array', 'attempted_at' => 'datetime', 'synced_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function batch(): BelongsTo { return $this->belongsTo(PosOfflineSyncBatch::class, 'sync_batch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
