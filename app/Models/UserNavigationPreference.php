<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNavigationPreference extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'hidden_module_ids',
        'pinned_module_ids',
        'module_order',
        'selected_preset',
    ];

    protected function casts(): array
    {
        return [
            'hidden_module_ids' => 'array',
            'pinned_module_ids' => 'array',
            'module_order' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
