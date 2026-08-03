<?php

namespace App\Models\Pos;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'require_open_session', 'tax_inclusive_pricing', 'tax_rounding_mode'])]
class PosBillingSetting extends Model
{
    protected function casts(): array
    {
        return [
            'require_open_session' => 'boolean',
            'tax_inclusive_pricing' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
