<?php

namespace App\Services\Finance;

use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\ExpenseTransaction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseLedgerService
{
    public function __construct(private readonly AuditLogger $audit, private readonly OutletAccessService $outlets) {}

    /** @param array<string,mixed> $data */
    public function createDraft(User $user, array $data): ExpenseTransaction
    {
        abort_unless($user->can('finance.expenses.create'), 403);
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->whereKey($data['expense_category_id'])->firstOrFail();
        if (! $category->is_active) throw ValidationException::withMessages(['expense_category_id' => 'Inactive categories cannot be used for new transactions.']);
        $branchId = $data['branch_id'] ?? null;
        if ($branchId === null && ! $this->outlets->hasCompanyWideAccess($user)) throw ValidationException::withMessages(['branch_id' => 'Only a company administrator can create a company-wide transaction.']);
        if ($branchId !== null && ! $this->outlets->canAccess($user, $user->company->branches()->findOrFail($branchId))) throw ValidationException::withMessages(['branch_id' => 'That outlet is not available to you.']);
        if (bccomp((string) $data['amount'], '0', 2) <= 0) throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);

        return DB::transaction(function () use ($user, $data, $category): ExpenseTransaction {
            $entry = ExpenseTransaction::query()->create([
                'company_id' => $user->company_id, 'branch_id' => $data['branch_id'] ?? null,
                'expense_category_id' => $category->id, 'classification_snapshot' => $category->classification,
                'transaction_date' => $data['transaction_date'], 'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $user->company->currency, 'payee' => $data['payee'] ?? null,
                'payment_method' => $data['payment_method'] ?? null, 'reference' => $data['reference'] ?? null,
                'description' => $data['description'], 'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null, 'status' => ExpenseTransaction::DRAFT, 'created_by' => $user->id,
            ]);
            $this->audit->record('finance.expense.created', $entry, 'Expense draft created.', ['company_id' => $user->company_id]);
            return $entry;
        });
    }

    public function post(ExpenseTransaction $entry, User $user): ExpenseTransaction
    {
        abort_unless($user->can('finance.expenses.post'), 403);
        return DB::transaction(function () use ($entry, $user): ExpenseTransaction {
            $entry = ExpenseTransaction::query()->lockForUpdate()->findOrFail($entry->id);
            if ($entry->company_id !== $user->company_id) abort(404);
            if ($entry->branch_id !== null && ! $this->outlets->canAccess($user, $entry->branch)) abort(403);
            if ($entry->branch_id === null && ! $this->outlets->hasCompanyWideAccess($user)) abort(403);
            if ($entry->status !== ExpenseTransaction::DRAFT) throw ValidationException::withMessages(['status' => 'Only a draft transaction can be posted.']);
            $entry->update(['status' => ExpenseTransaction::POSTED, 'posted_by' => $user->id, 'posted_at' => now()]);
            $this->audit->record('finance.expense.posted', $entry, 'Expense transaction posted.', ['company_id' => $user->company_id]);
            return $entry->fresh();
        });
    }

    /** @param array<string,mixed> $data */
    public function updateDraft(ExpenseTransaction $entry, User $user, array $data): ExpenseTransaction
    {
        abort_unless($user->can('finance.expenses.update_draft'), 403);
        if ($entry->company_id !== $user->company_id) abort(404);
        if ($entry->status !== ExpenseTransaction::DRAFT) throw ValidationException::withMessages(['status' => 'Only a draft transaction can be edited.']);
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->whereKey($data['expense_category_id'])->firstOrFail();
        if (! $category->is_active) throw ValidationException::withMessages(['expense_category_id' => 'Inactive categories cannot be used for new transactions.']);
        $branchId = $data['branch_id'] ?? null;
        if ($branchId === null && ! $this->outlets->hasCompanyWideAccess($user)) throw ValidationException::withMessages(['branch_id' => 'Only a company administrator can create a company-wide transaction.']);
        if ($branchId !== null && ! $this->outlets->canAccess($user, $user->company->branches()->findOrFail($branchId))) throw ValidationException::withMessages(['branch_id' => 'That outlet is not available to you.']);
        if (bccomp((string) $data['amount'], '0', 2) <= 0) throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        $entry->update($data + ['classification_snapshot' => $category->classification]);
        return $entry->fresh();
    }

    public function reverse(ExpenseTransaction $entry, User $user, string $reason, ?string $transactionDate = null): ExpenseTransaction
    {
        abort_unless($user->can('finance.expenses.reverse'), 403);
        if (trim($reason) === '') throw ValidationException::withMessages(['reason' => 'A reversal reason is required.']);
        if ($transactionDate !== null) {
            try {
                $transactionDate = CarbonImmutable::createFromFormat('Y-m-d', $transactionDate, $user->company?->timezone ?: config('app.timezone'))->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['transaction_date' => 'Enter a valid reversal date.']);
            }
        }
        return DB::transaction(function () use ($entry, $user, $reason, $transactionDate): ExpenseTransaction {
            $original = ExpenseTransaction::query()->lockForUpdate()->findOrFail($entry->id);
            if ($original->company_id !== $user->company_id) abort(404);
            if ($original->branch_id !== null && ! $this->outlets->canAccess($user, $original->branch)) abort(403);
            if ($original->branch_id === null && ! $this->outlets->hasCompanyWideAccess($user)) abort(403);
            if ($original->status !== ExpenseTransaction::POSTED) throw ValidationException::withMessages(['status' => 'Only a posted transaction can be reversed.']);
            if (ExpenseTransaction::query()->where('reverses_expense_transaction_id', $original->id)->exists()) throw ValidationException::withMessages(['status' => 'This transaction has already been reversed.']);
            $reversal = $original->replicate(['status', 'posted_by', 'posted_at', 'reverses_expense_transaction_id', 'reversed_by', 'reversed_at', 'reversal_reason']);
            $reversal->forceFill([
                'status' => ExpenseTransaction::POSTED,
                'amount' => bcmul((string) $original->amount, '-1', 2),
                'transaction_date' => $transactionDate ?: now($user->company?->timezone ?: config('app.timezone'))->toDateString(),
                'reverses_expense_transaction_id' => $original->id,
                'created_by' => $user->id,
                'posted_by' => $user->id,
                'posted_at' => now(),
                'reversal_reason' => $reason,
            ]);
            $reversal->save();
            $original->update(['status' => ExpenseTransaction::REVERSED, 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            $this->audit->record('finance.expense.reversed', $original, 'Expense transaction reversed.', ['company_id' => $user->company_id, 'reversal_id' => $reversal->id]);
            return $reversal;
        });
    }
}
