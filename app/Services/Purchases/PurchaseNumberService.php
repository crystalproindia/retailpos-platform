<?php

namespace App\Services\Purchases;

use App\Models\Purchases\PurchaseSettings;
use Illuminate\Support\Facades\DB;

class PurchaseNumberService
{
    public function settings(int $companyId): PurchaseSettings
    {
        return PurchaseSettings::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'po_prefix' => 'PO',
                'pr_prefix' => 'PR',
                'grn_prefix' => 'GRN',
                'return_prefix' => 'PRN',
            ],
        );
    }

    public function next(int $companyId, string $type): string
    {
        return DB::transaction(function () use ($companyId, $type): string {
            $this->settings($companyId);
            $settings = PurchaseSettings::query()->where('company_id', $companyId)->lockForUpdate()->firstOrFail();

            [$prefixField, $numberField] = match ($type) {
                'po' => ['po_prefix', 'next_po_number'],
                'grn' => ['grn_prefix', 'next_grn_number'],
                'return' => ['return_prefix', 'next_return_number'],
                default => ['pr_prefix', 'next_pr_number'],
            };

            $number = sprintf('%s-%06d', $settings->{$prefixField}, $settings->{$numberField});
            $settings->increment($numberField);

            return $number;
        });
    }
}
