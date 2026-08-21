<?php

namespace App\Services\Reports;

use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\Inventory\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PosProfitabilityBackfillService
{
    /** @return array{inspected:int,reconstructed:int,unavailable:int,skipped:int,last_id:int|null} */
    public function run(?int $companyId = null, bool $dryRun = true, int $chunkSize = 200, ?int $afterId = null): array
    {
        $result = ['inspected' => 0, 'reconstructed' => 0, 'unavailable' => 0, 'skipped' => 0, 'last_id' => null];
        $query = PosSaleItem::query()
            ->whereNull('cost_snapshot_status')
            ->whereHas('sale', fn (Builder $sale) => $sale->where('status', 'completed'))
            ->when($companyId, fn (Builder $items) => $items->where('company_id', $companyId))
            ->when($afterId, fn (Builder $items) => $items->whereKey('>', $afterId));

        $query->orderBy('id')->chunkById($chunkSize, function ($items) use (&$result, $dryRun): void {
            foreach ($items as $item) {
                $result['inspected']++;
                $result['last_id'] = $item->id;
                $snapshot = $this->snapshot($item);
                if ($snapshot === null) {
                    $result['unavailable']++;
                    if (! $dryRun) $item->update(['cost_snapshot_status' => 'unavailable']);
                    continue;
                }
                $result['reconstructed']++;
                if (! $dryRun) DB::transaction(fn () => PosSaleItem::query()->whereKey($item->id)->whereNull('cost_snapshot_status')->update($snapshot));
            }
        }, 'id');

        return $result;
    }

    /** @return array<string, string>|null */
    private function snapshot(PosSaleItem $item): ?array
    {
        // Old stock movements are authoritative only if they identify one sale line unambiguously.
        $movements = StockMovement::query()
            ->where('company_id', $item->company_id)
            ->where('reference_type', PosSale::class)
            ->where('reference_id', $item->pos_sale_id)
            ->where('movement_type', 'sale')
            ->where('product_id', $item->product_id)
            ->get(['quantity', 'unit_cost']);
        if ($movements->count() !== 1 || $movements->first()->unit_cost === null || $this->thousandths($movements->first()->quantity) !== $this->thousandths($item->quantity)) return null;

        $unitCost = $this->minor($movements->first()->unit_cost);
        $totalCost = $this->quantityValue($item->quantity, $unitCost);
        $gross = $this->minor($item->gross_amount ?? $item->line_total) ?: $this->minor($item->taxable_amount) + $this->minor($item->discount_amount);
        $net = $this->minor($item->taxable_amount ?? $item->line_total);
        if ($net < 0) return null;
        $profitBeforeDiscount = $gross - $totalCost;
        $profit = $net - $totalCost;

        return [
            'unit_cost_snapshot' => $this->decimal($unitCost), 'total_cost_snapshot' => $this->decimal($totalCost),
            'gross_sales_snapshot' => $this->decimal($gross), 'net_sales_snapshot' => $this->decimal($net),
            'gross_profit_before_discount' => $this->decimal($profitBeforeDiscount), 'gross_profit_snapshot' => $this->decimal($profit),
            'gross_margin_before_discount_percent' => $this->margin($profitBeforeDiscount, $gross), 'gross_margin_percent_snapshot' => $this->margin($profit, $net),
            'cost_snapshot_method' => 'stock_movement', 'cost_snapshot_status' => 'reconstructed',
        ];
    }

    private function minor(mixed $value): int
    {
        $value = (string) ($value ?? '0'); $negative = str_starts_with($value, '-'); $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, ''); $amount = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        return $negative ? -$amount : $amount;
    }

    private function thousandths(mixed $value): int
    {
        $value = (string) ($value ?? '0'); $negative = str_starts_with($value, '-'); $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, ''); $amount = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
        return $negative ? -$amount : $amount;
    }

    private function quantityValue(mixed $quantity, int $unitCost): int
    {
        $numerator = $this->thousandths($quantity) * $unitCost;
        return $numerator < 0 ? -intdiv(abs($numerator) + 500, 1000) : intdiv($numerator + 500, 1000);
    }

    private function decimal(int $minor): string { return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT); }
    private function margin(int $profit, int $sales): string { if ($sales === 0) return '0.0000'; $scaled = intdiv((abs($profit) * 1000000) + intdiv(abs($sales), 2), abs($sales)); return intdiv($profit < 0 ? -$scaled : $scaled, 10000).'.'.str_pad((string) abs(($profit < 0 ? -$scaled : $scaled) % 10000), 4, '0', STR_PAD_LEFT); }
}
