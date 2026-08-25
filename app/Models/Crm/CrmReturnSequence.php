<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'branch_id', 'financial_year', 'last_sequence'])]
class CrmReturnSequence extends Model
{
    protected function casts(): array
    {
        return ['last_sequence' => 'integer'];
    }
}
