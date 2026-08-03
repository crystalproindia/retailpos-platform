<?php

namespace App\Models\Ai;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Customers\Customer;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['forecast_run_id', 'company_id', 'outlet_id', 'product_id', 'customer_id', 'lead_id', 'period_start', 'period_end', 'predicted_value', 'lower_bound', 'upper_bound', 'score', 'classification', 'explanation', 'supporting_metrics'])]
class AiForecastResult extends Model
{
    protected function casts(): array { return ['period_start' => 'date', 'period_end' => 'date', 'predicted_value' => 'decimal:3', 'lower_bound' => 'decimal:3', 'upper_bound' => 'decimal:3', 'score' => 'decimal:2', 'explanation' => 'array', 'supporting_metrics' => 'array']; }
    public function run() { return $this->belongsTo(AiForecastRun::class, 'forecast_run_id'); }
    public function company() { return $this->belongsTo(Company::class); }
    public function outlet() { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function lead() { return $this->belongsTo(CrmLead::class); }
}
