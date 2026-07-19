<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\Pos\PosRegister;
use App\Models\Pos\PosRegisterSession;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosRegisterService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): PosRegister
    {
        $branch = Branch::query()->where('company_id', $user->company_id)->findOrFail($data['branch_id']);

        if (! $branch->is_active) {
            throw ValidationException::withMessages(['branch_id' => 'Inactive branches cannot have a POS register.']);
        }

        $register = PosRegister::create([
            'company_id' => $user->company_id,
            'branch_id' => $branch->id,
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
            if (! $register->is_active || ! $branch->is_active) {
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
            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'This register session is already closed.']);
            }
            $cashSales = PosSale::query()->where('company_id', $user->company_id)->where('register_session_id', $session->id)->where('status', 'completed')->whereHas('payments', fn ($payments) => $payments->where('payment_method', 'cash')->whereIn('status', ['recorded', 'confirmed']))->with('payments')->get()->sum(fn (PosSale $sale) => $sale->payments->where('payment_method', 'cash')->whereIn('status', ['recorded', 'confirmed'])->sum('amount'));
            $expected = round((float) $session->opening_cash + (float) $cashSales, 2);
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
        if (! $branch->is_active) {
            throw ValidationException::withMessages(['branch_id' => 'Inactive branches cannot create POS sales.']);
        }

        return PosRegisterSession::query()->where('company_id', $user->company_id)->where('register_id', $register->id)->where('id', $register->current_session_id)->where('status', 'open')->lockForUpdate()->firstOrFail();
    }
}
