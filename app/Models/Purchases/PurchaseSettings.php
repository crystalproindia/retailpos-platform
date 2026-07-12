<?php

namespace App\Models\Purchases;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'po_prefix', 'pr_prefix', 'grn_prefix', 'return_prefix', 'next_po_number', 'next_pr_number', 'next_grn_number', 'next_return_number', 'require_po_approval', 'require_purchase_request_approval', 'require_return_approval', 'default_payment_terms', 'default_tax_inclusive', 'allow_receive_without_po', 'auto_create_pr_from_reorder'])]
class PurchaseSettings extends Model
{
    protected $table = 'purchase_settings';

    protected function casts(): array
    {
        return [
            'require_po_approval' => 'boolean',
            'require_purchase_request_approval' => 'boolean',
            'require_return_approval' => 'boolean',
            'default_tax_inclusive' => 'boolean',
            'allow_receive_without_po' => 'boolean',
            'auto_create_pr_from_reorder' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
