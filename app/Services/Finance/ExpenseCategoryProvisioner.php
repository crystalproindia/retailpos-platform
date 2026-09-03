<?php

namespace App\Services\Finance;

use App\Models\Company;
use App\Models\Finance\ExpenseCategory;

class ExpenseCategoryProvisioner
{
    /** @var list<string> */
    private const DEFAULTS = ['Rent', 'Salaries & Wages', 'Electricity', 'Water', 'Internet & Telephone', 'Marketing & Advertising', 'Transportation', 'Repairs & Maintenance', 'Office Expenses', 'Professional Fees', 'Bank Charges', 'Travel', 'Insurance', 'Software & Subscriptions', 'Cleaning', 'Security', 'Staff Welfare', 'Miscellaneous'];

    public function provision(Company $company): void
    {
        foreach (self::DEFAULTS as $order => $name) {
            ExpenseCategory::query()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true, 'is_system' => true, 'sort_order' => $order + 1],
            );
        }
    }
}
