<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosRefund;
use App\Models\Pos\PosRegister;
use App\Models\Pos\PosRegisterSession;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosRegisterService
{
    public function __construct(private readonly AuditLogger $audit, private readonly OutletAccessService $outlets) {}

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): PosRegister
    {
        $branch = Branch::query()->where('company_id', $user->company_id)->findOrFail($data['branch_id']);

        if (! $this->outlets->canAccess($user, $branch)) {
            throw ValidationException::withMessages(['branch_id' => 'You are not assigned to this outlet.']);
        }
        $warehouseQuery = Warehouse::query()->where('company_id', $user->company_id)->where('branch_id', $branch->id)->where('is_active', true);
        $warehouse = ! empty($data['warehouse_id'])
            ? (clone $warehouseQuery)->findOrFail((int) $data['warehouse_id'])
            : $warehouseQuery->orderByDesc('is_primary')->orderBy('id')->first();
        $warehouse ??= Warehouse::create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'name' => $branch->name.' Stock',
            'code' => 'POS-'.$branch->id,
            'type' => 'store',
            'country' => $branch->country,
            'is_primary' => true,
            'is_active' => true,
        ]);
        $locationId = $data['stock_location_id'] ?? null;
        if ($locationId && ! StockLocation::query()->where('company_id', $user->company_id)->where('warehouse_id', $warehouse->id)->where('is_active', true)->whereKey((int) $locationId)->exists()) {
            throw ValidationException::withMessages(['stock_location_id' => 'Choose a bin in the selected register warehouse.']);
        }

        $register = PosRegister::create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'stock_location_id' => $locationId,
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'],
            'receipt_prefix' => strtoupper((string) ($data['receipt_prefix'] ?? 'POS')),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $user->id,
        ]);
        $this->audit->record('pos.register.created', $register, 'POS register created', ['company_id' => $user->company_id]);

        return $register;
    }

    public function open(PosRegister $register, User $user, string|int|float $openingCash = '0', ?string $notes = null): PosRegisterSession
    {
        return DB::transaction(function () use ($register, $user, $openingCash, $notes): PosRegisterSession {
            $register = PosRegister::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($register->id);
            $branch = Branch::query()->where('company_id', $user->company_id)->findOrFail($register->branch_id);
            if (! $register->is_active || ! $this->outlets->canAccess($user, $branch)) {
                throw ValidationException::withMessages(['register' => 'The register and branch must be active before opening a session.']);
            }
            if ($register->current_session_id || $register->sessions()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['register' => 'This register already has an open session.']);
            }

            $session = PosRegisterSession::create([
                'company_id' => $user->company_id,
                'register_id' => $register->id,
                'branch_id' => $register->branch_id,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'status' => 'open',
                'notes' => $notes,
            ]);
            $register->update(['current_session_id' => $session->id]);
            $this->audit->record('pos.register.session_opened', $session, 'POS register session opened', ['company_id' => $user->company_id, 'register_id' => $register->id]);

            return $session;
        });
    }

    public function close(PosRegisterSession $session, User $user, string|int|float $closingCash, ?string $notes = null): PosRegisterSession
    {
        return DB::transaction(function () use ($session, $user, $closingCash, $notes): PosRegisterSession {
            $session = PosRegisterSession::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($session->id);
            $branch = Branch::query()->where('company_id', $user->company_id)->findOrFail($session->branch_id);
            if (! $this->outlets->canAccess($user, $branch)) {
                throw ValidationException::withMessages(['outlet' => 'You are not assigned to this register outlet.']);
            }
            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'This register session is already closed.']);
            }
            $cashSales = PosSale::query()->where('company_id', $user->company_id)->where('register_session_id', $session->id)->where('status', 'completed')->whereHas('payments', fn ($payments) => $payments->where('payment_method', 'cash')->whereIn('status', ['recorded', 'confirmed']))->with('payments')->get()->sum(fn (PosSale $sale) => $sale->payments->where('payment_method', 'cash')->whereIn('status', ['recorded', 'confirmed'])->sum('amount'));
            $cashRefunds = PosRefund::query()->where('company_id', $user->company_id)->where('method', 'cash')->where('status', 'recorded')->whereHas('posReturn.originalSale', fn ($sales) => $sales->where('register_session_id', $session->id))->sum('amount');
            $expected = round((float) $session->opening_cash + (float) $cashSales - (float) $cashRefunds, 2);
            $variance = round((float) $closingCash - $expected, 2);
            $session->update(['closed_by' => $user->id, 'closed_at' => now(), 'closing_cash' => $closingCash, 'expected_cash' => $expected, 'variance' => $variance, 'status' => 'closed', 'notes' => $notes ?? $session->notes]);
            $session->register()->update(['current_session_id' => null]);
            $this->audit->record('pos.register.session_closed', $session, 'POS register session closed', ['company_id' => $user->company_id, 'register_id' => $session->register_id]);

            return $session->refresh();
        });
    }

    public function activeSession(User $user, int $registerId, int $branchId): PosRegisterSession
    {
        $register = PosRegister::query()->where('company_id', $user->company_id)->where('branch_id', $branchId)->where('is_active', true)->lockForUpdate()->findOrFail($registerId);
        $branch = Branch::query()->where('company_id', $user->company_id)->findOrFail($branchId);
        if (! $this->outlets->canAccess($user, $branch)) {
            throw ValidationException::withMessages(['branch_id' => 'You are not assigned to this outlet.']);
        }

        return PosRegisterSession::query()->where('company_id', $user->company_id)->where('register_id', $register->id)->where('id', $register->current_session_id)->where('status', 'open')->lockForUpdate()->firstOrFail();
    }
}
