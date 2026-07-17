<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'name', 'email', 'phone', 'status', 'last_login_at', 'created_by'])]
class CrmCustomerPortalUser extends Model
{
    protected function casts(): array
    {
        return ['last_login_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CrmCustomer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(CrmCustomerPortalToken::class, 'customer_portal_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
