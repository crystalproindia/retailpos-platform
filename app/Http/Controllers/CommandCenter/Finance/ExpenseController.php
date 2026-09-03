<?php

namespace App\Http\Controllers\CommandCenter\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\ExpenseCategory;
use App\Models\Finance\ExpenseTransaction;
use App\Services\Finance\ExpenseCategoryProvisioner;
use App\Services\Finance\ExpenseLedgerService;
use App\Services\Finance\ExpenseReceiptService;
use App\Services\Finance\FinancialPeriodResolver;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request, OutletAccessService $outlets, FinancialPeriodResolver $periods): View
    {
        abort_unless($request->user()->can('finance.expenses.view'), 403);

        return view('command-center.finance.expenses.index', [
            'entries' => $this->query($request, $outlets, $periods)
                ->latest('transaction_date')
                ->paginate(25)
                ->withQueryString(),
            'categories' => ExpenseCategory::query()
                ->where('company_id', $request->user()->company_id)
                ->orderBy('name')
                ->get(),
            'outlets' => $outlets->accessibleOutlets($request->user()),
        ]);
    }

    public function csv(Request $request, OutletAccessService $outlets, FinancialPeriodResolver $periods)
    {
        abort_unless($request->user()->can('finance.profit_and_loss.export'), 403);

        $query = $this->query($request, $outlets, $periods)->with(['category', 'branch', 'creator']);

        return Response::streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Transaction Date', 'Category', 'Classification', 'Outlet', 'Payee', 'Payment Method', 'Reference', 'Description', 'Amount', 'Status', 'Created By', 'Posted At']);

            $query->orderBy('transaction_date')->lazyById(250)->each(function (ExpenseTransaction $entry) use ($handle): void {
                $safe = fn ($value) => preg_match('/^[=+\-@]/', (string) $value) ? "'".$value : $value;

                fputcsv($handle, array_map($safe, [
                    $entry->transaction_date?->toDateString(),
                    $entry->category?->name,
                    $entry->classification_snapshot,
                    $entry->branch?->name ?? 'Company-wide',
                    $entry->payee,
                    $entry->payment_method,
                    $entry->reference,
                    $entry->description,
                    $entry->amount,
                    $entry->status,
                    $entry->creator?->name,
                    $entry->posted_at?->toDateTimeString(),
                ]));
            });

            fclose($handle);
        }, 'expenses.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request, OutletAccessService $outlets, ExpenseCategoryProvisioner $categories): View
    {
        abort_unless($request->user()->can('finance.expenses.create'), 403);
        $categories->provision($request->user()->company);

        return view('command-center.finance.expenses.form', [
            'entry' => new ExpenseTransaction(['transaction_date' => now($request->user()->company->timezone)->toDateString()]),
            'categories' => ExpenseCategory::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'outlets' => $outlets->accessibleOutlets($request->user()),
        ]);
    }

    public function store(Request $request, ExpenseLedgerService $ledger)
    {
        $data = $this->data($request);
        $entry = $ledger->createDraft($request->user(), collect($data)->except(['receipt', 'post_now'])->all());

        if ($request->hasFile('receipt')) {
            app(ExpenseReceiptService::class)->replaceDraft($entry, $request->user(), $request->file('receipt'));
        }

        if ($request->boolean('post_now')) {
            $ledger->post($entry, $request->user());
        }

        return redirect()->route('finance.expenses.show', $entry)->with('status', 'Expense saved.');
    }

    public function show(Request $request, ExpenseTransaction $expense): View
    {
        abort_unless($request->user()->can('finance.expenses.view'), 403);
        abort_unless($expense->company_id === $request->user()->company_id, 404);

        return view('command-center.finance.expenses.show', ['entry' => $expense->load(['category', 'branch', 'creator'])]);
    }

    public function post(Request $request, ExpenseTransaction $expense, ExpenseLedgerService $ledger)
    {
        $ledger->post($expense, $request->user());

        return back()->with('status', 'Expense posted.');
    }

    public function reverse(Request $request, ExpenseTransaction $expense, ExpenseLedgerService $ledger)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'transaction_date' => ['nullable', 'date'],
        ]);
        $ledger->reverse($expense, $request->user(), $data['reason'], $data['transaction_date'] ?? null);

        return back()->with('status', 'Expense reversed with a linked correcting entry.');
    }

    private function query(Request $request, OutletAccessService $outlets, FinancialPeriodResolver $periods): Builder
    {
        $query = ExpenseTransaction::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['category', 'branch'])
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('expense_category_id', $request->integer('category_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), fn (Builder $query) => $query->where(fn (Builder $search) => $search
                ->where('payee', 'like', '%'.$request->input('search').'%')
                ->orWhere('reference', 'like', '%'.$request->input('search').'%')));

        if ($request->filled('period') || $request->filled('date_from') || $request->filled('date_to')) {
            $range = $periods->resolve($request->user()->company, [
                'period' => $request->input('period', 'custom'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ]);
            $query->whereDate('transaction_date', '>=', $range['from']->toDateString())
                ->whereDate('transaction_date', '<=', $range['to']->toDateString());
        }

        $available = $outlets->accessibleOutlets($request->user());

        if (! $outlets->hasCompanyWideAccess($request->user())) {
            $query->whereIn('branch_id', $available->modelKeys());
        } elseif ($request->filled('outlet_id') && $request->input('outlet_id') !== 'all') {
            $query->where('branch_id', $request->integer('outlet_id'));
        }

        return $query;
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'transaction_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'integer'],
            'amount' => ['required', 'decimal:0,2'],
            'branch_id' => ['nullable', 'integer'],
            'payee' => ['nullable', 'string', 'max:160'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,application/pdf'],
            'post_now' => ['nullable', 'boolean'],
        ]);
    }
}
