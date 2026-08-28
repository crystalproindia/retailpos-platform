<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\User;
use App\Services\Finance\CrmPaymentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmPaymentNumberMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_historical_receipts_and_initializes_from_the_highest_existing_number(): void
    {
        $migration = require database_path('migrations/2026_08_28_020000_create_crm_payment_number_sequences.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('crm_payment_number_sequences'));

        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'HISTORICAL-INVOICE-001',
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'grand_total' => '100.00',
            'balance_due' => '100.00',
        ]);
        $year = (int) now()->format('Y');

        foreach ([1, 2, 7] as $number) {
            CrmInvoicePayment::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'invoice_id' => $invoice->id,
                'payment_reference' => 'HISTORICAL-PAY-'.$number,
                'receipt_number' => sprintf('RPOS-RCPT-%d-%05d', $year, $number),
                'amount' => '1.00',
                'allocated_amount' => '0.00',
                'unallocated_amount' => '0.00',
                'currency' => 'INR',
                'payment_date' => today(),
                'payment_method' => 'cash',
                'status' => 'recorded',
                'recorded_by' => $user->id,
                'idempotency_key' => hash('sha256', 'migration-history-'.$number),
            ]);
        }

        $migration->up();
        $this->assertTrue(Schema::hasTable('crm_payment_number_sequences'));

        $next = DB::transaction(fn (): string => app(CrmPaymentNumberService::class)->nextReceiptNumber($year));

        $this->assertSame(sprintf('RPOS-RCPT-%d-00008', $year), $next);
        $this->assertDatabaseHas('crm_invoice_payments', ['receipt_number' => sprintf('RPOS-RCPT-%d-00007', $year)]);
        $this->assertDatabaseHas('crm_payment_number_sequences', [
            'scope_key' => 'global',
            'sequence_type' => 'receipt_number',
            'calendar_year' => $year,
            'last_sequence' => 8,
        ]);
    }
}
