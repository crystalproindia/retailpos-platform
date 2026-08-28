<?php

namespace App\Models\Finance;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'scope_key', 'sequence_type', 'calendar_year', 'last_sequence'])]
class CrmPaymentNumberSequence extends Model
{
    protected function casts(): array
    {
        return ['last_sequence' => 'integer'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
