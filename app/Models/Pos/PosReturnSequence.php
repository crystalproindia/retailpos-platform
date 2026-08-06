<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'branch_id', 'financial_year', 'last_return_sequence', 'last_credit_note_sequence'])]
class PosReturnSequence extends Model {}
