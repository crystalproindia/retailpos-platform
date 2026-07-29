<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\SupplierPayment;
use Illuminate\Database\Eloquent\Builder;

class SupplierPayableService
{
    /** @return array{outstanding:string,advances:string,net_payable:string} */
    public function summary(int $companyId, ?int $supplierId = null, ?int $branchId = null): array
    {
        $invoices = PurchaseInvoice::query()->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_paid', 'overdue']);
        $payments = SupplierPayment::query()->where('company_id', $companyId)->where('status', 'recorded');
        if ($supplierId) { $invoices->where('supplier_id', $supplierId); $payments->where('supplier_id', $supplierId); }
        if ($branchId) { $invoices->where('branch_id', $branchId); $payments->where('branch_id', $branchId); }
        $outstanding = (int) round((float) $invoices->sum('outstanding_total') * 100);
        $advances = (int) round((float) $payments->sum('unallocated_amount') * 100);
        return ['outstanding' => $this->decimal($outstanding), 'advances' => $this->decimal($advances), 'net_payable' => $this->decimal(max(0, $outstanding - $advances))];
    }

    /** @return array<string, int> */
    public function ageing(int $companyId, ?int $supplierId = null): array
    {
        $query = PurchaseInvoice::query()->where('company_id', $companyId)->whereIn('status', ['approved', 'partially_paid', 'overdue'])->whereNotNull('due_date');
        if ($supplierId) { $query->where('supplier_id', $supplierId); }
        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $query->select(['due_date', 'outstanding_total'])->orderBy('due_date')->each(function (PurchaseInvoice $invoice) use (&$buckets): void {
            $days = max(0, $invoice->due_date->diffInDays(today(), false));
            $key = $days === 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : ($days <= 90 ? '61_90' : '90_plus')));
            $buckets[$key] += (int) round((float) $invoice->outstanding_total * 100);
        });
        return $buckets;
    }

    private function decimal(int $paise): string { return number_format($paise / 100, 2, '.', ''); }
}
