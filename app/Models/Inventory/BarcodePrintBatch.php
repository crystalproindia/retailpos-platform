<?php

namespace App\Models\Inventory;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'template_id', 'batch_number', 'title', 'created_by', 'status', 'total_labels', 'printed_at'])]
class BarcodePrintBatch extends Model
{
    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BarcodeLabelTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BarcodePrintBatchItem::class, 'print_batch_id');
    }
}
