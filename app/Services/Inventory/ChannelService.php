<?php

namespace App\Services\Inventory;

use App\Events\Domain\Inventory\ChannelSyncWarning;
use App\Models\Inventory\ChannelProductMapping;
use App\Models\Inventory\ChannelStockLevel;
use App\Models\Inventory\InventorySyncLog;
use App\Models\Inventory\SalesChannel;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;

class ChannelService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveChannel(User $user, array $data, ?SalesChannel $channel = null): SalesChannel
    {
        $payload = $data + ['company_id' => $user->company_id];
        $model = $channel ? tap($channel)->update($payload) : SalesChannel::create($payload);
        $this->auditLogger->record($channel ? 'inventory.channel.updated' : 'inventory.channel.created', $model, 'Inventory sales channel saved');

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveMapping(User $user, SalesChannel $channel, array $data): ChannelProductMapping
    {
        $mapping = ChannelProductMapping::updateOrCreate(
            [
                'sales_channel_id' => $channel->id,
                'product_id' => $data['product_id'],
            ],
            $data + [
                'company_id' => $user->company_id,
                'sales_channel_id' => $channel->id,
            ],
        );

        ChannelStockLevel::updateOrCreate(
            [
                'sales_channel_id' => $channel->id,
                'product_id' => $mapping->product_id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
            ],
            [
                'company_id' => $user->company_id,
                'listed_quantity' => $data['listed_quantity'] ?? 0,
                'reserved_quantity' => $data['reserved_quantity'] ?? 0,
                'available_quantity' => $data['available_quantity'] ?? 0,
                'buffer_quantity' => $data['stock_buffer_quantity'] ?? 0,
                'sync_status' => 'not_synced',
            ],
        );

        $this->auditLogger->record('inventory.channel.mapping_saved', $mapping, 'Channel product mapping saved');

        return $mapping->refresh();
    }

    public function logWarning(User $user, SalesChannel $channel, string $message): InventorySyncLog
    {
        $log = InventorySyncLog::create([
            'company_id' => $user->company_id,
            'sales_channel_id' => $channel->id,
            'action' => 'inventory_sync',
            'status' => 'warning',
            'message' => $message,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->domainEvents->dispatch(new ChannelSyncWarning(
            companyId: $user->company_id,
            actorId: $user->id,
            aggregateType: InventorySyncLog::class,
            aggregateId: $log->id,
            payload: [
                'sales_channel_id' => $channel->id,
                'channel_name' => $channel->name,
                'message' => $message,
            ],
        ));

        return $log;
    }
}
