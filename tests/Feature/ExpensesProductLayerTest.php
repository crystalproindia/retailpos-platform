<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Finance\ExpenseCategory;
use App\Models\User;
use App\Services\Finance\ExpenseLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesProductLayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_the_expense_and_profit_and_loss_screens(): void
    {
        [$user] = $this->fixture(UserRole::Administrator);

        $this->actingAs($user)->get(route('finance.expenses.index'))->assertOk()->assertSee('Expenses');
        $this->actingAs($user)->get(route('finance.expense-categories.index'))->assertOk()->assertSee('Expense Categories');
        $this->actingAs($user)->get(route('finance.profit-and-loss.index'))->assertOk()->assertSee('Profit & Loss');
    }

    public function test_staff_cannot_access_finance_expense_product_screens(): void
    {
        [$user] = $this->fixture(UserRole::Staff);

        $this->actingAs($user)->get(route('finance.expenses.index'))->assertForbidden();
        $this->actingAs($user)->get(route('finance.profit-and-loss.index'))->assertForbidden();
    }

    public function test_add_expense_provisions_default_categories_for_an_existing_company(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Administrator]);

        $this->actingAs($user)->get(route('finance.expenses.create'))
            ->assertOk()
            ->assertSee('Rent')
            ->assertSee('Salaries &amp; Wages', false);

        $this->assertSame(18, ExpenseCategory::query()->where('company_id', $company->id)->count());
    }

    public function test_company_wide_category_drill_down_keeps_the_authorized_period_and_shows_matching_rows(): void
    {
        [$user, $branch] = $this->fixture(UserRole::Administrator);
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->where('name', 'Rent')->firstOrFail();
        $entry = app(ExpenseLedgerService::class)->createDraft($user, [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'transaction_date' => now('Asia/Kolkata')->toDateString(),
            'amount' => '123.45',
            'payee' => 'Category drill-down payee',
            'description' => 'P&L drill-down test entry',
        ]);
        app(ExpenseLedgerService::class)->post($entry, $user);

        $this->actingAs($user)->get(route('finance.expenses.index', [
            'period' => 'today',
            'outlet_id' => 'all',
            'category_id' => $category->id,
        ]))
            ->assertOk()
            ->assertSee('Category drill-down payee')
            ->assertSee('Rent');
    }

    public function test_authorized_user_can_open_an_expense_detail_page(): void
    {
        [$user, $branch] = $this->fixture(UserRole::Administrator);
        $category = ExpenseCategory::query()->where('company_id', $user->company_id)->firstOrFail();
        $entry = app(ExpenseLedgerService::class)->createDraft($user, [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'transaction_date' => now('Asia/Kolkata')->toDateString(),
            'amount' => '45.00',
            'payee' => 'Detail page payee',
            'description' => 'Expense detail screen regression coverage',
        ]);

        $this->actingAs($user)->get(route('finance.expenses.show', $entry))
            ->assertOk()
            ->assertSee('Detail page payee')
            ->assertSee('Expense detail screen regression coverage');
    }

    /** @return array{User, Branch} */
    private function fixture(UserRole $role): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => $role]);
        ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent', 'classification' => ExpenseCategory::OPERATING_EXPENSE, 'is_active' => true]);

        return [$user, $branch];
    }
}
