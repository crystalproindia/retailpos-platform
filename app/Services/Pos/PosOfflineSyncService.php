<?php

namespace App\Services\Pos;

use App\Events\Domain\Pos\PosDomainEvent;
use App\Models\Customers\Customer;
use App\Models\Inventory\Product;
use App\Models\Pos\PosOfflineSetting;
use App\Models\Pos\PosOfflineSyncBatch;
use App\Models\Pos\PosOfflineSyncRecord;
use App\Models\User;
use App\Services\Customers\CustomerNumberService;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;

class PosOfflineSyncService
{
    public function __construct(private readonly PosCheckoutService $checkout, private readonly CustomerNumberService $customerNumbers, private readonly DomainEventDispatcher $events) {}

    public function settings(int $companyId): PosOfflineSetting
    {
        return PosOfflineSetting::firstOrCreate(['company_id' => $companyId], ['enable_offline_pos' => true, 'enable_auto_sync' => true, 'allow_offline_cash' => true, 'allow_offline_manual_card' => true, 'allow_offline_manual_upi' => true, 'allow_offline_wallet_usage' => false, 'allow_offline_loyalty_redemption' => false, 'offline_stock_conflict_strategy' => 'sync_with_warning', 'offline_data_cache_minutes' => 60]);
    }

    /** @return array<string, mixed> */
    public function bootstrap(User $user): array
    {
        $products = Product::query()->with(['category', 'brand', 'stockLevels' => fn ($query) => $query->where('branch_id', $user->branch_id)])->where('company_id', $user->company_id)->where('is_active', true)->where('status', Product::STATUS_ACTIVE)->orderBy('name')->limit(500)->get()->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'price' => (float) $product->selling_price, 'category' => $product->category?->name, 'category_id' => $product->category_id, 'brand' => $product->brand?->name, 'image' => $product->image, 'track_inventory' => (bool) $product->track_inventory, 'available_stock' => (float) $product->stockLevels->sum('quantity_available')]);
        $customers = Customer::query()->with(['groups.group', 'insight'])->where('company_id', $user->company_id)->where('is_active', true)->latest('last_purchase_at')->limit(500)->get()->map(fn (Customer $customer) => ['id' => $customer->id, 'name' => $customer->display_name, 'mobile' => $customer->phone ?: $customer->whatsapp, 'group' => $customer->groups->first()?->group?->name, 'loyalty_points' => $customer->loyalty_points_balance, 'wallet_balance' => (float) $customer->wallet_balance, 'last_purchase_at' => $customer->last_purchase_at?->toDateString(), 'retention_note' => $customer->insight?->segment_label]);

        return ['generated_at' => now()->toIso8601String(), 'products' => $products, 'customers' => $customers, 'settings' => $this->settings($user->company_id)->only(['enable_offline_pos', 'enable_auto_sync', 'allow_offline_cash', 'allow_offline_manual_card', 'allow_offline_manual_upi', 'allow_offline_wallet_usage', 'allow_offline_loyalty_redemption', 'offline_data_cache_minutes', 'max_offline_bill_amount'])];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function sync(User $user, array $data): array
    {
        $batch = PosOfflineSyncBatch::firstOrCreate(['company_id' => $user->company_id, 'batch_uuid' => $data['batch_uuid']], ['branch_id' => $user->branch_id, 'user_id' => $user->id, 'device_id' => $data['device_id'] ?? null, 'status' => 'processing', 'total_records' => count($data['records']), 'started_at' => now()]);
        $this->events->dispatch(new PosDomainEvent('pos.offline.sync.started', $user->company_id, $user->id, PosOfflineSyncBatch::class, $batch->id, ['batch_uuid' => $batch->batch_uuid, 'total_records' => count($data['records'])]));
        $results = collect($data['records'])->map(fn (array $record) => $this->syncRecord($user, $batch, $record, $data['device_id'] ?? null))->all();
        $synced = collect($results)->whereIn('status', ['synced', 'warning'])->count(); $failed = collect($results)->where('status', 'failed')->count();
        $batch->update(['synced_records' => $synced, 'failed_records' => $failed, 'status' => $failed ? ($synced ? 'partially_failed' : 'failed') : 'completed', 'completed_at' => now()]);
        $this->events->dispatch(new PosDomainEvent($failed ? 'pos.offline.sync.failed' : 'pos.offline.sync.completed', $user->company_id, $user->id, PosOfflineSyncBatch::class, $batch->id, ['batch_uuid' => $batch->batch_uuid, 'synced_records' => $synced, 'failed_records' => $failed]));

        return ['batch_uuid' => $batch->batch_uuid, 'status' => $batch->status, 'results' => $results];
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    public function syncRecord(User $user, PosOfflineSyncBatch $batch, array $record, ?string $deviceId = null): array
    {
        $existing = PosOfflineSyncRecord::query()->where('company_id', $user->company_id)->where('offline_uuid', $record['offline_uuid'])->first();
        if ($existing && in_array($existing->status, ['synced', 'warning', 'duplicate'], true)) return ['offline_uuid' => $record['offline_uuid'], 'status' => 'duplicate', 'sale_id' => $existing->server_reference_id, 'sale_number' => $existing->metadata['sale_number'] ?? null];
        $sync = $existing ?: PosOfflineSyncRecord::create(['company_id' => $user->company_id, 'sync_batch_id' => $batch->id, 'user_id' => $user->id, 'offline_uuid' => $record['offline_uuid'], 'device_id' => $deviceId, 'record_type' => 'bill', 'payload' => $record, 'status' => 'processing', 'attempted_at' => now()]);
        try {
            return DB::transaction(function () use ($user, $record, $sync, $deviceId): array {
                $customerId = $this->resolveCustomer($user, $record['customer'] ?? null);
                $warnings = $this->warnings($user, $record);
                $sale = $this->checkout->complete($user, ['branch_id' => $user->branch_id, 'customer_id' => $customerId, 'items' => $record['items'], 'payments' => $record['payments'], 'coupon_code' => $record['coupon_code'] ?? null, 'notes' => $record['notes'] ?? null, 'device_type' => 'mobile', 'offline_uuid' => $record['offline_uuid'], 'offline_reference' => $record['offline_reference'], 'synced_from_offline' => true, 'offline_created_at' => $record['offline_created_at'] ?? now(), 'device_id' => $deviceId]);
                $status = $warnings ? 'warning' : 'synced';
                $sync->update(['status' => $status, 'server_reference_type' => $sale::class, 'server_reference_id' => $sale->id, 'warning_message' => $warnings ? implode(' ', $warnings) : null, 'synced_at' => now(), 'metadata' => ['sale_number' => $sale->sale_number]]);
                $this->events->dispatch(new PosDomainEvent($warnings ? 'pos.offline.sync.warning' : 'pos.offline.bill.queued', $user->company_id, $user->id, $sale::class, $sale->id, ['offline_uuid' => $record['offline_uuid'], 'sale_number' => $sale->sale_number, 'warning' => $warnings ? implode(' ', $warnings) : null]));

                return ['offline_uuid' => $record['offline_uuid'], 'status' => $status, 'sale_id' => $sale->id, 'sale_number' => $sale->sale_number, 'warning' => $warnings ? implode(' ', $warnings) : null];
            });
        } catch (\Throwable $exception) {
            $sync->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'attempted_at' => now()]);
            $this->events->dispatch(new PosDomainEvent('pos.offline.sync.record_failed', $user->company_id, $user->id, PosOfflineSyncRecord::class, $sync->id, ['offline_uuid' => $record['offline_uuid']]));
            return ['offline_uuid' => $record['offline_uuid'], 'status' => 'failed', 'error' => 'The offline bill could not be synced.'];
        }
    }

    /** @param array<string, mixed>|null $offlineCustomer */
    private function resolveCustomer(User $user, ?array $offlineCustomer): ?int
    {
        if (! $offlineCustomer || empty($offlineCustomer['mobile'])) return null;
        $customer = Customer::query()->where('company_id', $user->company_id)->where(fn ($query) => $query->where('phone', $offlineCustomer['mobile'])->orWhere('whatsapp', $offlineCustomer['mobile']))->first();
        if ($customer) return $customer->id;
        $customer = Customer::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'customer_number' => $this->customerNumbers->next($user->company_id), 'first_name' => $offlineCustomer['name'] ?? 'Offline', 'display_name' => $offlineCustomer['name'] ?? 'Offline customer', 'phone' => $offlineCustomer['mobile'], 'whatsapp' => $offlineCustomer['mobile'], 'customer_type' => 'retail', 'status' => 'active', 'created_by' => $user->id]);
        return $customer->id;
    }

    /** @param array<string, mixed> $record @return array<int, string> */
    private function warnings(User $user, array $record): array
    {
        $warnings = [];
        foreach ($record['items'] as $item) { $product = Product::query()->where('company_id', $user->company_id)->find($item['product_id']); if ($product && isset($item['unit_price']) && (float) $item['unit_price'] !== (float) $product->selling_price) $warnings[] = 'Offline price differs from the current product price.'; }
        return array_unique($warnings);
    }
}
