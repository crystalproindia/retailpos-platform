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
        $outstanding = $this->paise($invoices->sum('outstanding_total'));
        $advances = $this->paise($payments->sum('unallocated_amount'));
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
            $buckets[$key] += $this->paise($invoice->outstanding_total);
        });
        return $buckets;
    }

    private function paise(string|int|float $value): int
    {
        $value = trim((string) $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';
        $paise = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if (isset($fraction[2]) && $fraction[2] >= '5') {
            $paise++;
        }

        return $negative ? -$paise : $paise;
    }

    private function decimal(int $paise): string
    {
        $negative = $paise < 0;
        $digits = str_pad((string) abs($paise), 3, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
