<?php

namespace App\Models\Crm;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['crm_invoice_return_id', 'original_invoice_item_id', 'product_id', 'product_name_snapshot', 'sku_snapshot', 'hsn_sac_snapshot', 'unit_snapshot', 'original_quantity', 'previously_returned_quantity', 'return_quantity', 'unit_price_snapshot', 'gross_reversal', 'discount_reversal', 'taxable_reversal', 'tax_reversal', 'cgst_reversal', 'sgst_reversal', 'igst_reversal', 'cess_reversal', 'credit_total', 'cost_status', 'unit_cost_snapshot', 'cogs_reversal', 'gross_profit_reversal', 'restock_requested', 'inventory_disposition', 'condition_note'])]
class CrmInvoiceReturnItem extends Model
{
    protected function casts(): array
    {
        return [
            'original_quantity' => 'decimal:3', 'previously_returned_quantity' => 'decimal:3', 'return_quantity' => 'decimal:3',
            'unit_price_snapshot' => 'decimal:2', 'gross_reversal' => 'decimal:2', 'discount_reversal' => 'decimal:2',
            'taxable_reversal' => 'decimal:2', 'tax_reversal' => 'decimal:2', 'cgst_reversal' => 'decimal:2',
            'sgst_reversal' => 'decimal:2', 'igst_reversal' => 'decimal:2', 'cess_reversal' => 'decimal:2',
            'credit_total' => 'decimal:2', 'unit_cost_snapshot' => 'decimal:2', 'cogs_reversal' => 'decimal:2',
            'gross_profit_reversal' => 'decimal:2', 'restock_requested' => 'boolean',
        ];
    }

    public function crmInvoiceReturn(): BelongsTo
    {
        return $this->belongsTo(CrmInvoiceReturn::class);
    }

    public function originalInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(CrmInvoiceItem::class, 'original_invoice_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
