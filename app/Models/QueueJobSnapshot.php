<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['queue', 'pending_count', 'failed_count', 'processed_count', 'reserved_count', 'captured_at'])]
class QueueJobSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }
}
