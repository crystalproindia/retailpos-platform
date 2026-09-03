<?php

namespace App\Models\Finance;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseTransaction extends Model
{
    public const DRAFT = 'draft';
    public const POSTED = 'posted';
    public const REVERSED = 'reversed';

    protected $guarded = [];
    protected function casts(): array { return ['transaction_date' => 'date', 'posted_at' => 'datetime', 'reversed_at' => 'datetime', 'amount' => 'decimal:2']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reverses_expense_transaction_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
