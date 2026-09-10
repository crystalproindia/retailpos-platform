<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\Crm\CustomerLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_lifecycle_precedence_uses_company_timezone_at_every_boundary(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::parse('2026-09-08 00:15:00', 'Asia/Kolkata')->startOfDay();
        CarbonImmutable::setTestNow($today);

        foreach ([29 => 'New Customer', 30 => 'New Customer', 31 => 'Active Customer', 89 => 'Active Customer', 90 => 'At Risk', 179 => 'At Risk', 180 => 'Dormant'] as $days => $expected) {
            $customer = $this->customer($user, "Boundary {$days}");
            $this->invoice($user, $customer, $today->subDays($days), InvoiceStatus::Issued);

            $lifecycle = app(CustomerLifecycleService::class)->classify($user->fresh('company'), $customer);

            $this->assertSame($expected, $lifecycle['status'], "day {$days}");
            $this->assertSame(1, $lifecycle['recognized_invoice_count']);
        }
    }

    public function test_dormancy_precedes_repeat_and_only_recent_recognized_invoices_make_a_customer_repeat(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::parse('2026-09-08 09:00:00', 'Asia/Kolkata')->startOfDay();
        CarbonImmutable::setTestNow($today);

        $oldOnly = $this->customer($user, 'Multiple old invoices');
        $this->invoice($user, $oldOnly, $today->subDays(181), InvoiceStatus::Paid);
        $this->invoice($user, $oldOnly, $today->subDays(190), InvoiceStatus::Credited);
        $this->assertSame('Dormant', app(CustomerLifecycleService::class)->classify($user->fresh('company'), $oldOnly)['status']);

        $oneRecent = $this->customer($user, 'One recent invoice');
        $this->invoice($user, $oneRecent, $today->subDays(20), InvoiceStatus::Issued);
        $this->invoice($user, $oneRecent, $today->subDays(181), InvoiceStatus::Credited);
        $lifecycle = app(CustomerLifecycleService::class)->classify($user->fresh('company'), $oneRecent);
        $this->assertSame('New Customer', $lifecycle['status']);
        $this->assertSame(1, $lifecycle['recent_recognized_invoice_count']);

        $repeat = $this->customer($user, 'Two recent invoices');
        $this->invoice($user, $repeat, $today->subDays(20), InvoiceStatus::Issued);
        $this->invoice($user, $repeat, $today->subDays(60), InvoiceStatus::Credited);
        $this->assertSame('Repeat Customer', app(CustomerLifecycleService::class)->classify($user->fresh('company'), $repeat)['status']);
    }

    public function test_draft_cancelled_and_void_invoices_do_not_invent_recognized_history(): void
    {
        $user = $this->user();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-08 09:00:00', 'Asia/Kolkata'));
        $customer = $this->customer($user, 'Excluded histories');

        foreach ([InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Void] as $status) {
            $this->invoice($user, $customer, CarbonImmutable::now('Asia/Kolkata')->subDays(1), $status);
        }

        $lifecycle = app(CustomerLifecycleService::class)->classify($user->fresh('company'), $customer);
        $this->assertSame('Prospect', $lifecycle['status']);
        $this->assertSame(0, $lifecycle['recognized_invoice_count']);
    }

    public function test_a_credited_or_amended_invoice_remains_one_recognized_invoice_under_finance_semantics(): void
    {
        $user = $this->user();
        $today = CarbonImmutable::parse('2026-09-08 09:00:00', 'Asia/Kolkata');
        CarbonImmutable::setTestNow($today);
        $customer = $this->customer($user, 'Credited and amended history');
        $invoice = $this->invoice($user, $customer, $today->subDays(20), InvoiceStatus::Credited);
        $invoice->update(['amendment_version' => 2]);

        $lifecycle = app(CustomerLifecycleService::class)->classify($user->fresh('company'), $customer);

        // A finalized return/credit or correction changes finance amounts, not
        // the fact that the original invoice was a recognized customer event.
        $this->assertSame('New Customer', $lifecycle['status']);
        $this->assertSame(1, $lifecycle['recognized_invoice_count']);
    }

    private function user(): User
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function customer(User $user, string $name): CrmCustomer
    {
        return CrmCustomer::create([
            'company_id' => $user->company_id,
            'customer_code' => 'CLC-'.(CrmCustomer::query()->count() + 1),
            'company_name' => $name,
            'display_name' => $name,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function invoice(User $user, CrmCustomer $customer, CarbonImmutable $issueDate, InvoiceStatus $status): CrmInvoice
    {
        return CrmInvoice::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'customer_id' => $customer->id,
            'invoice_number' => 'CLI-'.str_pad((string) (CrmInvoice::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'currency' => 'INR',
            'grand_total' => 1000,
            'balance_due' => 1000,
            'status' => $status,
            'issue_date' => $issueDate->toDateString(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
