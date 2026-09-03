<?php

namespace App\Models\Finance;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    public const OPERATING_EXPENSE = 'operating_expense';
    public const OTHER_EXPENSE = 'other_expense';
    public const OTHER_INCOME = 'other_income';

    protected $guarded = [];

    protected function casts(): array { return ['is_active' => 'boolean', 'is_system' => 'boolean']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function transactions(): HasMany { return $this->hasMany(ExpenseTransaction::class, 'expense_category_id'); }
}
