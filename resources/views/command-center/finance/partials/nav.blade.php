<nav class="mb-6 flex gap-2 overflow-x-auto pb-1" aria-label="Finance navigation">
    @foreach ([['Receivables', 'finance.receivables.index', 'finance.receivables.view'], ['Payables', 'finance.payables.index', 'finance.payables.view'], ['Expenses', 'finance.expenses.index', 'finance.expenses.view'], ['Expense Categories', 'finance.expense-categories.index', 'finance.expense_categories.manage'], ['Profit & Loss', 'finance.profit-and-loss.index', 'finance.profit_and_loss.view'], ['Reconciliation', 'finance.reconciliation.index', 'finance.reconciliation.manage']] as [$label, $route, $permission])
        @can($permission)
            <a href="{{ route($route) }}" class="whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-semibold transition {{ request()->routeIs(str($route)->beforeLast('.').'.*') ? 'border-teal-500 bg-teal-50 text-teal-800 dark:bg-teal-950/40 dark:text-teal-200' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300' }}">{{ $label }}</a>
        @endcan
    @endforeach
</nav>
