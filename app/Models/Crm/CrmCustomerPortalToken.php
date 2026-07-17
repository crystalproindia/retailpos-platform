<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_portal_user_id', 'token_hash', 'purpose', 'expires_at', 'used_at'])]
class CrmCustomerPortalToken extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(CrmCustomerPortalUser::class, 'customer_portal_user_id');
    }
}
