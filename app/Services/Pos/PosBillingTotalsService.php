<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\Compliance\GstSetting;
use App\Models\Customers\Customer;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\Compliance\GstTaxCalculator;
use App\Services\Promotions\PromotionSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Produces the immutable POS amount snapshots. All sale money is calculated in
 * paise; browser values are used only as an authorized input to that process.
 */
class PosBillingTotalsService
{
    public function __construct(
        private readonly GstTaxCalculator $taxes,
        private readonly PosBillingSettingsService $settings,
        private readonly PromotionSettingsService $promotionSettings,
    ) {}

    /**
     * @param array<int, array{product: Product, quantity: mixed, unit_price?: mixed, discount_type?: string, discount_value?: mixed}> $items
     * @param array<string, mixed> $data
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, string>, place_of_supply_state_code: ?string, tax_treatment_snapshot: ?string}
     */
    public function calculate(User $user, Branch $branch, ?Customer $customer, array $items, array $data): array
    {
        $billing = $this->settings->settings($user->company_id);
        $lines = [];
        $preOrderSubtotal = 0;

        foreach ($items as $item) {
            $product = $item['product'];
            $quantity = $this->quantity($item['quantity']);
            $configuredPrice = $this->money($product->selling_price);
            $unitPrice = array_key_exists('unit_price', $item) ? $this->money($item['unit_price']) : $configuredPrice;

            if ($unitPrice !== $configuredPrice && ! $user->can('pos.price.override')) {
                throw new AuthorizationException('You are not permitted to change product prices.');
            }

            $gross = $this->multiply($quantity, $unitPrice);
            $lineDiscount = $this->lineDiscount($user, $gross, $item);
            $netBeforeOrder = max(0, $gross - $lineDiscount);
            $preOrderSubtotal += $netBeforeOrder;

            $lines[] = [
                'product' => $product,
                'quantity_milli' => $quantity,
                'quantity' => $this->decimalQuantity($quantity),
                'unit_price_minor' => $unitPrice,
                'gross_minor' => $gross,
                'line_discount_minor' => $lineDiscount,
                'net_before_order_minor' => $netBeforeOrder,
            ];
        }

        $orderDiscount = $this->orderDiscount($user, $preOrderSubtotal, $data);
        $this->enforceDiscountCap($user, $preOrderSubtotal, $orderDiscount);
        $requiresTaxSetup = collect($lines)->contains(fn (array $line): bool => $this->rate($line['product']->taxRate?->rate ?? '0') > 0);
        $gst = $requiresTaxSetup ? GstSetting::query()->where('company_id', $user->company_id)->first() : null;
        $supplierState = $gst?->state_code ?: $this->stateCode($branch->state) ?: $this->stateCode($user->company?->state);
        $placeOfSupply = $this->stateCode($data['place_of_supply_state_code'] ?? null)
            ?: $this->stateCode($customer?->state)
            ?: $gst?->default_place_of_supply_state_code
            ?: $supplierState;

        if ($requiresTaxSetup && (! $gst || ! $gst->state_code || ! $supplierState || ! $placeOfSupply)) {
            throw ValidationException::withMessages(['tax' => 'Configure the business GST state and a valid place of supply before completing this taxable sale.']);
        }

        $totals = ['subtotal' => 0, 'item_discount_total' => 0, 'order_discount_total' => 0, 'taxable_amount' => 0, 'tax_amount' => 0, 'cgst_total' => 0, 'sgst_total' => 0, 'igst_total' => 0, 'cess_total' => 0, 'total_amount' => 0];
        $remainingOrderDiscount = $orderDiscount;
        $taxTreatment = null;

        foreach ($lines as $index => &$line) {
            $allocation = $index === array_key_last($lines)
                ? $remainingOrderDiscount
                : $this->divide($orderDiscount * $line['net_before_order_minor'], max(1, $preOrderSubtotal));
            $remainingOrderDiscount -= $allocation;
            $discounted = max(0, $line['net_before_order_minor'] - $allocation);
            $rate = $this->rate($line['product']->taxRate?->rate ?? '0');
            $grossTax = $rate > 0
                ? $this->taxes->calculate($this->decimal($line['gross_minor']), $this->decimalRate($rate), $gst?->state_code, $placeOfSupply, $billing->tax_inclusive_pricing)
                : ['taxable_value' => $this->decimal($line['gross_minor'])];
            $tax = $rate > 0
                ? $this->taxes->calculate($this->decimal($discounted), $this->decimalRate($rate), $gst?->state_code, $placeOfSupply, $billing->tax_inclusive_pricing)
                : ['taxable_value' => $this->decimal($discounted), 'cgst' => '0.00', 'sgst' => '0.00', 'igst' => '0.00', 'cess' => '0.00', 'tax_total' => '0.00', 'line_total' => $this->decimal($discounted), 'treatment' => 'not_taxable'];

            $line['order_discount_minor'] = $allocation;
            $line['discount_amount_minor'] = $line['line_discount_minor'] + $allocation;
            $line['gross_taxable_minor'] = $this->money($grossTax['taxable_value']);
            $line['taxable_minor'] = $this->money($tax['taxable_value']);
            $line['cgst_minor'] = $this->money($tax['cgst']);
            $line['sgst_minor'] = $this->money($tax['sgst']);
            $line['igst_minor'] = $this->money($tax['igst']);
            $line['cess_minor'] = $this->money($tax['cess']);
            $line['tax_minor'] = $this->money($tax['tax_total']);
            $line['line_total_minor'] = $this->money($tax['line_total']);
            $line['tax_rate'] = $this->decimalRate($rate);
            $line['tax_treatment'] = $tax['treatment'];
            $taxTreatment = $taxTreatment && $taxTreatment !== $tax['treatment'] ? 'mixed' : $tax['treatment'];

            $totals['subtotal'] += $line['gross_minor'];
            $totals['item_discount_total'] += $line['line_discount_minor'];
            $totals['order_discount_total'] += $allocation;
            $totals['taxable_amount'] += $line['taxable_minor'];
            $totals['tax_amount'] += $line['tax_minor'];
            $totals['cgst_total'] += $line['cgst_minor'];
            $totals['sgst_total'] += $line['sgst_minor'];
            $totals['igst_total'] += $line['igst_minor'];
            $totals['cess_total'] += $line['cess_minor'];
            $totals['total_amount'] += $line['line_total_minor'];
        }
        unset($line);

        return [
            'lines' => $lines,
            'totals' => collect($totals)->map(fn (int $value) => $this->decimal($value))->all(),
            'place_of_supply_state_code' => $placeOfSupply,
            'tax_treatment_snapshot' => $taxTreatment,
        ];
    }

    /** @param array<string, mixed> $item */
    private function lineDiscount(User $user, int $gross, array $item): int
    {
        $type = $item['discount_type'] ?? null;
        $value = $item['discount_value'] ?? null;
        if (! $type || $value === null || $this->moneyOrRate($value) === 0) return 0;
        if (! $user->can('pos.discount.apply')) throw new AuthorizationException('You are not permitted to apply discounts.');

        $discount = match ($type) {
            'fixed' => $this->money($value),
            'percentage' => $this->percentage($gross, $this->rate($value)),
            default => throw ValidationException::withMessages(['items' => 'Line discount type must be fixed or percentage.']),
        };
        if ($discount > $gross) throw ValidationException::withMessages(['items' => 'A line discount cannot exceed the line amount.']);

        return $discount;
    }

    /** @param array<string, mixed> $data */
    private function orderDiscount(User $user, int $subtotal, array $data): int
    {
        $value = $data['manual_discount_amount'] ?? $data['manual_discount_value'] ?? '0';
        if ($this->moneyOrRate($value) === 0) return 0;
        if (! $user->can('pos.discount.apply')) throw new AuthorizationException('You are not permitted to apply discounts.');
        $type = $data['manual_discount_type'] ?? 'fixed';
        $discount = $type === 'percentage' ? $this->percentage($subtotal, $this->rate($value)) : $this->money($value);
        if ($discount > $subtotal) throw ValidationException::withMessages(['manual_discount_amount' => 'The order discount cannot exceed the cart subtotal.']);

        return $discount;
    }

    private function enforceDiscountCap(User $user, int $subtotal, int $orderDiscount): void
    {
        if ($orderDiscount === 0 || $user->can('pos.discount.override')) return;
        $settings = $this->promotionSettings->settings($user->company_id);
        $amountCap = $settings->max_discount_amount_per_bill === null ? null : $this->money($settings->max_discount_amount_per_bill);
        $percentageCap = $settings->max_discount_percentage_per_bill === null ? null : $this->percentage($subtotal, $this->rate($settings->max_discount_percentage_per_bill));
        $cap = $amountCap !== null && $percentageCap !== null ? min($amountCap, $percentageCap) : ($amountCap ?? $percentageCap);
        if ($cap !== null && $orderDiscount > $cap) throw ValidationException::withMessages(['manual_discount_amount' => 'This order discount requires a manager override.']);
    }

    private function money(mixed $value): int
    {
        return $this->parse((string) $value, 2, 'amount');
    }

    private function moneyOrRate(mixed $value): int
    {
        return $this->parse((string) $value, 3, 'amount');
    }

    private function quantity(mixed $value): int
    {
        $quantity = $this->parse((string) $value, 3, 'quantity');
        if ($quantity < 1) throw ValidationException::withMessages(['items' => 'Quantity must be greater than zero.']);

        return $quantity;
    }

    private function rate(mixed $value): int
    {
        return $this->parse((string) $value, 3, 'rate');
    }

    private function parse(string $value, int $scale, string $field): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,'. $scale .'}))?$/', trim($value), $matches)) {
            throw ValidationException::withMessages([$field => 'A valid non-negative decimal value is required.']);
        }

        return ((int) $matches[1] * (10 ** $scale)) + (int) str_pad($matches[2] ?? '', $scale, '0');
    }

    private function multiply(int $quantityMilli, int $unitPriceMinor): int
    {
        return intdiv(($quantityMilli * $unitPriceMinor) + 500, 1000);
    }

    private function percentage(int $amountMinor, int $rateMilli): int
    {
        return intdiv(($amountMinor * $rateMilli) + 50000, 100000);
    }

    private function divide(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function decimalQuantity(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function decimalRate(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function stateCode(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^[0-9]{2}$/', $value) ? $value : null;
    }
}
