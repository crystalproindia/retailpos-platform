<?php

namespace App\Models;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'employee_id', 'warehouse_id', 'is_active'])]
class WorkforceEmployeeWarehouseAssignment extends Model
{
    protected $table = 'workforce_employee_warehouse_assignments';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(WorkforceEmployee::class, 'employee_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
