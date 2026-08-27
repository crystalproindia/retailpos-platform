<?php

namespace App\Models\Crm;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['amendment_id', 'invoice_item_id', 'product_id', 'warehouse_id', 'name_snapshot', 'sku_snapshot', 'hsn_sac_snapshot', 'quantity_snapshot', 'unit_snapshot', 'unit_price_snapshot', 'discount_snapshot', 'taxable_snapshot', 'tax_snapshot', 'line_total_snapshot', 'cost_status_snapshot', 'unit_cost_snapshot'])]
class CrmInvoiceAmendmentItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_snapshot' => 'decimal:3', 'unit_price_snapshot' => 'decimal:2', 'discount_snapshot' => 'decimal:2',
            'taxable_snapshot' => 'decimal:2', 'tax_snapshot' => 'decimal:2', 'line_total_snapshot' => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:2',
        ];
    }

    public function amendment(): BelongsTo
    {
        return $this->belongsTo(CrmInvoiceAmendment::class, 'amendment_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(CrmInvoiceItem::class, 'invoice_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
