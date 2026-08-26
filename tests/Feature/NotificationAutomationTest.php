<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\ProformaStatus;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\NotificationAutomationSetting;
use App\Models\NotificationConditionState;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Notifications\AutomationNotificationService;
use App\Services\Notifications\NotificationAutomationEvaluator;
use App\Services\Notifications\NotificationAutomationSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class NotificationAutomationTest extends TestCase
{
    use BuildsReportingData;
    use RefreshDatabase;

    public function test_active_condition_is_deduplicated_recovers_and_can_notify_on_a_later_cycle(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $settings = $this->settings($company);
        $condition = $this->condition($branch);
        $service = app(AutomationNotificationService::class);

        $first = $service->sync($company, $settings, 'inventory_stock', [$condition]);
        $second = $service->sync($company, $settings, 'inventory_stock', [$condition]);
        $recovery = $service->sync($company, $settings, 'inventory_stock', []);
        $third = $service->sync($company, $settings, 'inventory_stock', [$condition]);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $recovery['recovered']);
        $this->assertSame(1, $third['created']);
        $this->assertSame(2, $admin->notifications()->count());
        $this->assertSame(2, NotificationDelivery::where('channel', 'database')->count());
        $state = NotificationConditionState::sole();
        $this->assertTrue($state->is_active);
        $this->assertSame(2, $state->cycle);
    }

    public function test_outlet_alert_reaches_admin_and_assigned_manager_but_not_another_outlet_manager(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $otherBranch = Branch::factory()->for($company)->create();
        $assigned = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $outside = User::factory()->for($company)->create(['branch_id' => $otherBranch->id, 'role' => UserRole::Manager]);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $assigned->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $admin->id]);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $otherBranch->id, 'user_id' => $outside->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $admin->id]);

        app(AutomationNotificationService::class)->sync($company, $this->settings($company), 'inventory_stock', [$this->condition($branch)]);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $assigned->notifications()->count());
        $this->assertSame(0, $outside->notifications()->count());
    }

    public function test_receivable_uses_current_balance_and_paid_or_cancelled_invoices_do_not_alert(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $settings = $this->settings($company, ['payment_overdue_days' => [1, 7, 30]]);
        $companyToday = now($company->timezone)->startOfDay();
        $due = $this->invoice($company, $branch, $admin, 'INV-DUE', '485.00', InvoiceStatus::PartiallyPaid, $companyToday->copy()->subDays(7));
        $this->invoice($company, $branch, $admin, 'INV-PAID', '0.00', InvoiceStatus::Paid, $companyToday->copy()->subDays(8));
        $this->invoice($company, $branch, $admin, 'INV-CANCELLED', '900.00', InvoiceStatus::Cancelled, $companyToday->copy()->subDays(8));

        app(NotificationAutomationEvaluator::class)->evaluate($company);

        $notification = $admin->notifications()->sole();
        $this->assertStringContainsString('₹485.00 overdue by 7 days', $notification->data['message']);
        $this->assertSame('receivable', $notification->data['metadata']['category']);
        $this->assertSame($due->id, $notification->data['aggregate_id']);
        $this->assertDatabaseCount('notification_condition_states', 1);
    }

    public function test_credit_adjusted_zero_balance_does_not_trigger_a_payment_reminder(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $this->settings($company);
        $invoice = $this->invoice($company, $branch, $admin, 'INV-CREDITED', '0.00', InvoiceStatus::Credited, today()->subDays(10));
        $invoice->update(['grand_total' => '1000.00', 'credited_total' => '1000.00']);

        app(NotificationAutomationEvaluator::class)->evaluate($company);

        $this->assertSame(0, $admin->notifications()->count());
        $this->assertDatabaseMissing('notification_condition_states', ['condition_type' => 'receivable']);
    }

    public function test_active_quotation_and_proforma_expiry_alert_while_terminal_documents_are_skipped(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $this->settings($company);
        $lead = $this->lead($company, $branch, $admin);
        $quotation = CrmQuotation::create(['company_id' => $company->id, 'lead_id' => $lead->id, 'quotation_number' => 'QT-OPEN', 'title' => 'Open', 'customer_name' => 'Asha', 'currency' => 'INR', 'grand_total' => '128000.00', 'valid_until' => today()->addDay(), 'status' => QuotationStatus::Sent, 'created_by' => $admin->id]);
        CrmQuotation::create(['company_id' => $company->id, 'lead_id' => $lead->id, 'quotation_number' => 'QT-DONE', 'title' => 'Done', 'currency' => 'INR', 'valid_until' => today()->subDay(), 'status' => QuotationStatus::Converted, 'created_by' => $admin->id]);
        $proforma = CrmProformaInvoice::create(['company_id' => $company->id, 'proforma_number' => 'PI-OPEN', 'title' => 'Open', 'customer_name' => 'Bala', 'currency' => 'INR', 'grand_total' => '25000.00', 'balance_amount' => '20000.00', 'invoice_date' => today(), 'due_date' => today(), 'status' => ProformaStatus::Sent, 'created_by' => $admin->id]);
        CrmProformaInvoice::create(['company_id' => $company->id, 'proforma_number' => 'PI-PAID', 'title' => 'Paid', 'currency' => 'INR', 'invoice_date' => today(), 'due_date' => today()->subDay(), 'status' => ProformaStatus::Paid, 'created_by' => $admin->id]);

        app(NotificationAutomationEvaluator::class)->evaluate($company);

        $notifications = $admin->notifications;
        $this->assertCount(2, $notifications);
        $this->assertTrue($notifications->contains(fn ($item) => $item->data['aggregate_id'] === $quotation->id && $item->data['metadata']['category'] === 'quotation'));
        $this->assertTrue($notifications->contains(fn ($item) => $item->data['aggregate_id'] === $proforma->id && $item->data['metadata']['category'] === 'proforma'));
    }

    public function test_email_not_configured_preserves_in_app_alert_and_records_safe_skip_once(): void
    {
        Bus::fake();
        config(['mail.default' => 'array', 'mail.from.address' => null]);
        [$company, $branch, $admin] = $this->companyFixture();
        $settings = $this->settings($company, ['internal_email_enabled' => true]);

        $service = app(AutomationNotificationService::class);
        $service->sync($company, $settings, 'inventory_stock', [$this->condition($branch)]);
        $service->sync($company, $settings, 'inventory_stock', [$this->condition($branch)]);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertDatabaseHas('notification_deliveries', ['company_id' => $company->id, 'channel' => 'email', 'status' => 'skipped_not_configured']);
        $this->assertSame(2, NotificationDelivery::count());
        $this->assertStringNotContainsString('password', (string) NotificationDelivery::where('channel', 'email')->value('failure_reason'));
    }

    public function test_customer_payment_email_is_explicitly_opt_in_and_missing_address_skips_safely(): void
    {
        Bus::fake();
        config(['mail.default' => 'array', 'mail.from.address' => null]);
        [$company, $branch, $admin] = $this->companyFixture();
        $settings = $this->settings($company, ['customer_payment_emails_enabled' => false]);
        $invoice = $this->invoice($company, $branch, $admin, 'INV-EMAIL', '150.00', InvoiceStatus::Issued, today());
        $invoice->update(['billing_email' => 'customer@example.test']);

        app(NotificationAutomationEvaluator::class)->evaluate($company);
        $this->assertSame(0, NotificationDelivery::where('recipient', 'customer@example.test')->count());

        $settings->update(['customer_payment_emails_enabled' => true]);
        NotificationConditionState::query()->where('condition_type', 'receivable')->update(['is_active' => false]);
        app(NotificationAutomationEvaluator::class)->evaluate($company);
        $this->assertSame(1, NotificationDelivery::where('recipient', 'customer@example.test')->count());
        $this->assertSame('skipped_not_configured', NotificationDelivery::where('recipient', 'customer@example.test')->value('status'));
    }

    public function test_automation_settings_are_admin_only_tenant_scoped_and_whatsapp_remains_disabled(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $other = Company::factory()->create();
        $otherSettings = $this->settings($other, ['daily_summary_enabled' => true]);

        $this->actingAs($admin)->get(route('notifications.automation.edit'))->assertOk()->assertSee('WhatsApp')->assertSee('Planned');
        $this->actingAs($manager)->get(route('notifications.automation.edit'))->assertForbidden();
        $this->actingAs($admin)->put(route('notifications.automation.update'), $this->settingsPayload(['daily_summary_enabled' => '1', 'customer_payment_emails_enabled' => '1']))->assertRedirect();

        $updated = NotificationAutomationSetting::where('company_id', $company->id)->firstOrFail();
        $this->assertTrue($updated->daily_summary_enabled);
        $this->assertTrue($updated->customer_payment_emails_enabled);
        $this->assertTrue($otherSettings->fresh()->daily_summary_enabled);
        $this->assertDatabaseMissing('notification_preferences', ['whatsapp_enabled' => true]);
    }

    public function test_inbox_filter_mark_all_read_and_cross_tenant_scope_are_preserved(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        [$otherCompany, $otherBranch, $otherAdmin] = $this->companyFixture();
        app(AutomationNotificationService::class)->sync($company, $this->settings($company), 'inventory_stock', [$this->condition($branch)]);
        app(AutomationNotificationService::class)->sync($otherCompany, $this->settings($otherCompany), 'receivable', [$this->condition($otherBranch, ['category' => 'receivable'])]);

        $this->actingAs($admin)->get(route('notifications.index', ['category' => 'inventory']))->assertOk()->assertSee('Stock is running low')->assertDontSee('receivable reminder');
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertSame(1, $otherAdmin->unreadNotifications()->count());
    }

    public function test_low_stock_uses_inventory_intelligence_and_recovery_allows_a_new_alert(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $this->settings($company, ['reorder_enabled' => false]);
        $warehouse = $this->reportWarehouse($company, $branch, 'Alert warehouse');
        $product = $this->reportProduct($company, $branch, 'Wireless Keyboard', '50.00');
        $level = $this->reportStockLevel($company, $branch, $warehouse, $product, '3.000', '5.000');

        app(NotificationAutomationEvaluator::class)->evaluate($company);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('Wireless Keyboard has 3 remaining', $admin->notifications()->first()->data['message']);

        $level->update(['quantity_on_hand' => '10.000', 'quantity_available' => '10.000']);
        app(NotificationAutomationEvaluator::class)->evaluate($company);
        $this->assertFalse(NotificationConditionState::where('condition_type', 'inventory_stock')->sole()->is_active);

        $level->update(['quantity_on_hand' => '2.000', 'quantity_available' => '2.000']);
        app(NotificationAutomationEvaluator::class)->evaluate($company);
        $this->assertSame(2, $admin->notifications()->count());
    }

    public function test_daily_owner_summary_is_deterministic_admin_only_and_idempotent_without_ai(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-25 08:15:00', 'Asia/Kolkata'));
        [$company, $branch, $admin] = $this->companyFixture();
        $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $this->settings($company, ['daily_summary_enabled' => true, 'summary_time' => '08:00', 'internal_email_enabled' => false]);

        app(NotificationAutomationEvaluator::class)->evaluate($company);
        app(NotificationAutomationEvaluator::class)->evaluate($company);

        $summary = $admin->notifications()->where('data->metadata->category', 'owner_summary')->get();
        $this->assertCount(1, $summary);
        $this->assertStringContainsString('Net sales are', $summary->first()->data['message']);
        $this->assertSame(0, $manager->notifications()->where('data->metadata->category', 'owner_summary')->count());
        $this->assertSame(1, NotificationConditionState::where('condition_type', 'owner_summary')->count());
        CarbonImmutable::setTestNow();
    }

    public function test_reorder_reminder_comes_from_inventory_intelligence_without_creating_a_purchase_order(): void
    {
        [$company, $branch, $admin] = $this->companyFixture();
        $this->settings($company, ['low_stock_enabled' => false, 'out_of_stock_enabled' => false, 'reorder_enabled' => true, 'purchase_reminders_enabled' => true]);
        $warehouse = $this->reportWarehouse($company, $branch, 'Reorder warehouse');
        $product = $this->reportProduct($company, $branch, 'Fast Cable', '25.00');
        $level = $this->reportStockLevel($company, $branch, $warehouse, $product, '10.000', '5.000');
        $level->update(['maximum_stock' => '30.000', 'safety_stock' => '5.000', 'supplier_lead_time_days' => 7]);
        $this->reportStockMovement($company, $branch, $warehouse, $product, $admin, 'sale', 'out', '60.000', '70.000', '10.000', now()->subDays(2));

        app(NotificationAutomationEvaluator::class)->evaluate($company);

        $notification = $admin->notifications()->where('data->metadata->category', 'purchasing')->sole();
        $this->assertSame('A purchase recommendation is ready', $notification->data['title']);
        $this->assertStringContainsString('Fast Cable may need', $notification->data['message']);
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseHas('notification_condition_states', ['condition_type' => 'purchasing', 'stage' => 'recommended']);
    }

    /** @return array{Company,Branch,User} */
    private function companyFixture(): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $branch = Branch::factory()->for($company)->create();
        $admin = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator]);

        return [$company, $branch, $admin];
    }

    private function settings(Company $company, array $overrides = []): NotificationAutomationSetting
    {
        $settings = app(NotificationAutomationSettingsService::class)->forCompany($company);
        if ($overrides) {
            $settings->update($overrides);
        }

        return $settings->refresh();
    }

    private function invoice(Company $company, Branch $branch, User $user, string $number, string $balance, InvoiceStatus $status, $dueDate): CrmInvoice
    {
        return CrmInvoice::create([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'invoice_number' => $number,
            'billing_name' => 'Asha Buyer', 'billing_company' => 'ABC Traders', 'currency' => 'INR',
            'grand_total' => $balance, 'balance_due' => $balance, 'status' => $status,
            'issue_date' => today()->subMonth(), 'due_date' => $dueDate, 'created_by' => $user->id,
        ]);
    }

    private function condition(Branch $branch, array $overrides = []): array
    {
        return array_replace([
            'subject_type' => 'inventory_product_warehouse', 'subject_id' => 101, 'branch_id' => $branch->id,
            'stage' => 'low_stock', 'severity' => 'attention', 'category' => 'inventory', 'icon' => 'inventory',
            'title' => 'Stock is running low', 'message' => 'Wireless Keyboard has 3 remaining at Main Warehouse.',
            'action_url' => route('inventory.intelligence.index'), 'action_label' => 'Review Inventory',
            'context' => ['available' => 3],
        ], $overrides);
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_replace([
            'low_stock_enabled' => '1', 'out_of_stock_enabled' => '1', 'reorder_enabled' => '1',
            'payment_reminders_enabled' => '1', 'payment_before_due_days' => '3', 'payment_overdue_days' => '1, 7, 30',
            'quotation_expiry_enabled' => '1', 'proforma_expiry_enabled' => '1', 'document_expiry_notice_days' => '3',
            'purchase_reminders_enabled' => '1', 'summary_time' => '08:00', 'timezone' => 'Asia/Kolkata',
        ], $overrides);
    }

    private function lead(Company $company, Branch $branch, User $user): CrmLead
    {
        $source = CrmLeadSource::create(['company_id' => $company->id, 'name' => 'Manual', 'slug' => 'manual', 'is_active' => true]);
        $status = CrmLeadStatus::create(['company_id' => $company->id, 'name' => 'New', 'slug' => 'new', 'stage_type' => LeadStageType::New, 'is_active' => true]);

        return CrmLead::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'source_id' => $source->id, 'status_id' => $status->id, 'assigned_user_id' => $user->id, 'created_by' => $user->id, 'title' => 'Automation lead', 'priority' => LeadPriority::Medium]);
    }
}
