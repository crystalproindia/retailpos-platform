<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_id','product_id','category_id_snapshot','brand_id_snapshot','name','sku_snapshot','category_name_snapshot','brand_name_snapshot','description','hsn_sac','quantity','unit','unit_price','unit_cost_snapshot','total_cost_snapshot','gross_sales_snapshot','net_sales_snapshot','gross_profit_before_discount','gross_profit_snapshot','gross_margin_percent_snapshot','cost_snapshot_method','cost_snapshot_status','discount_type','discount_value','discount_amount','tax_rate','tax_treatment_snapshot','tax_amount','cgst_amount','sgst_amount','igst_amount','cess_amount','line_subtotal','line_total','sort_order'])]
class CrmInvoiceItem extends Model
{
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','unit_cost_snapshot'=>'decimal:2','total_cost_snapshot'=>'decimal:2','gross_sales_snapshot'=>'decimal:2','net_sales_snapshot'=>'decimal:2','gross_profit_before_discount'=>'decimal:2','gross_profit_snapshot'=>'decimal:2','gross_margin_percent_snapshot'=>'decimal:4','discount_value'=>'decimal:3','discount_amount'=>'decimal:2','tax_rate'=>'decimal:3','tax_amount'=>'decimal:2','cgst_amount'=>'decimal:2','sgst_amount'=>'decimal:2','igst_amount'=>'decimal:2','cess_amount'=>'decimal:2','line_subtotal'=>'decimal:2','line_total'=>'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(CrmInvoice::class, 'invoice_id'); }

    public function returnItems(): HasMany
    {
        return $this->hasMany(CrmInvoiceReturnItem::class, 'original_invoice_item_id');
    }
}
