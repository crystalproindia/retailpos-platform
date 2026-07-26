<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasIndustry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
