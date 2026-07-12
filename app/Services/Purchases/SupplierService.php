<?php

namespace App\Services\Purchases;

use App\Events\Domain\Purchases\PurchaseDomainEvent;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierAddress;
use App\Models\Purchases\SupplierContact;
use App\Models\Purchases\SupplierProduct;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly SupplierScoreService $scores,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Supplier
    {
        $supplier = Supplier::create($this->payload($user, $data));
        $this->auditLogger->record('purchase.supplier.created', $supplier, 'Supplier created');
        $this->dispatch('purchase.supplier.created', $supplier, $user, ['supplier_id' => $supplier->id, 'name' => $supplier->name]);

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, User $user, array $data): Supplier
    {
        $supplier->update($this->payload($user, $data, false));
        $this->auditLogger->record('purchase.supplier.updated', $supplier, 'Supplier updated');
        $this->dispatch('purchase.supplier.updated', $supplier, $user, ['supplier_id' => $supplier->id, 'name' => $supplier->name]);

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
        $this->auditLogger->record('purchase.supplier.deleted', $supplier, 'Supplier moved to trash');
    }

    public function restore(Supplier $supplier): Supplier
    {
        $supplier->restore();
        $this->auditLogger->record('purchase.supplier.restored', $supplier, 'Supplier restored');

        return $supplier->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveContact(User $user, Supplier $supplier, array $data): SupplierContact
    {
        return DB::transaction(function () use ($user, $supplier, $data): SupplierContact {
            if ((bool) ($data['is_primary'] ?? false)) {
                $supplier->contacts()->update(['is_primary' => false]);
            }

            $contact = SupplierContact::create($data + [
                'company_id' => $supplier->company_id,
                'supplier_id' => $supplier->id,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->auditLogger->record('purchase.supplier.contact_created', $contact, 'Supplier contact created');

            return $contact;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAddress(User $user, Supplier $supplier, array $data): SupplierAddress
    {
        return DB::transaction(function () use ($supplier, $data): SupplierAddress {
            if ((bool) ($data['is_default'] ?? false)) {
                $supplier->addresses()->update(['is_default' => false]);
            }

            $address = SupplierAddress::create($data + [
                'company_id' => $supplier->company_id,
                'supplier_id' => $supplier->id,
            ]);

            $this->auditLogger->record('purchase.supplier.address_created', $address, 'Supplier address created');

            return $address;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mapProduct(User $user, Supplier $supplier, array $data): SupplierProduct
    {
        return DB::transaction(function () use ($user, $supplier, $data): SupplierProduct {
            if ((bool) ($data['is_preferred'] ?? false)) {
                SupplierProduct::query()
                    ->where('company_id', $supplier->company_id)
                    ->where('product_id', $data['product_id'])
                    ->update(['is_preferred' => false]);
            }

            $mapping = SupplierProduct::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'product_id' => $data['product_id'],
                ],
                $data + [
                    'company_id' => $supplier->company_id,
                    'supplier_id' => $supplier->id,
                    'is_active' => (bool) ($data['is_active'] ?? true),
                    'is_preferred' => (bool) ($data['is_preferred'] ?? false),
                ],
            );

            $this->auditLogger->record('purchase.supplier_product.mapped', $mapping, 'Supplier product mapped');
            $this->scores->snapshot($supplier->refresh(), $user->id, 'Supplier product mapping updated.');

            return $mapping->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(User $user, array $data, bool $withCompany = true): array
    {
        $payload = $data;
        $payload['is_active'] = (bool) ($data['is_active'] ?? false);

        if ($withCompany) {
            $payload['company_id'] = $user->company_id;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(string $eventKey, Supplier $supplier, User $user, array $payload): void
    {
        $this->domainEvents->dispatch(new PurchaseDomainEvent(
            key: $eventKey,
            companyId: $supplier->company_id,
            actorId: $user->id,
            aggregateType: Supplier::class,
            aggregateId: $supplier->id,
            payload: $payload,
        ));
    }
}
