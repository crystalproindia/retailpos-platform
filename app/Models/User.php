<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmLead;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['company_id', 'branch_id', 'name', 'email', 'role', 'is_active', 'is_platform_admin', 'password', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'is_platform_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'role' => UserRole::class,
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  array<int, UserRole|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        $currentRole = $this->role instanceof UserRole ? $this->role->value : $this->role;

        return collect($roles)
            ->map(fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role)
            ->contains($currentRole);
    }

    public function isAdministrator(): bool
    {
        return $this->hasAnyRole([UserRole::Administrator]);
    }

    public function assignedCrmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'assigned_user_id');
    }

    public function createdCrmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'created_by');
    }

    public function assignedCrmCompanies(): HasMany
    {
        return $this->hasMany(CrmCompany::class, 'assigned_user_id');
    }

    public function assignedCrmContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'assigned_user_id');
    }

    public function assignedCrmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'assigned_user_id');
    }

    public function createdCrmActivities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'created_by');
    }

    public function createdCrmCustomers(): HasMany
    {
        return $this->hasMany(CrmCustomer::class, 'created_by');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
}
