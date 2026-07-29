<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'provider', 'name', 'account_email', 'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'settings', 'status', 'connected_by', 'connected_at', 'last_synced_at', 'last_sync_status', 'last_sync_error', 'disconnected_at'])]
class IntegrationConnection extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'settings' => 'array',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && filled($this->access_token);
    }
}
