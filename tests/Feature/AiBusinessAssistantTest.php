<?php

namespace Tests\Feature;

use App\Contracts\Ai\AiProviderInterface;
use App\Enums\UserRole;
use App\Models\Ai\AiAssistantInteraction;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Customers\Customer;
use App\Models\User;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiDateRangeResolver;
use App\Services\Ai\AiIntentRouter;
use App\Services\Reports\RetailReportingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class AiBusinessAssistantTest extends TestCase
{
    use BuildsReportingData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-08-25 10:00:00', 'Asia/Kolkata'));
        config(['ai.enabled' => false, 'ai.openai.api_key' => null]);
    }

    public function test_authorized_manager_can_open_friendly_assistant_without_provider_configuration(): void
    {
        [$company, $outlet, $manager] = $this->account(UserRole::Manager);

        $this->actingAs($manager)->get('/ai')->assertOk()
            ->assertSee('What would you like to know about your business?')
            ->assertSee('Your business today')
            ->assertSee('Needs your attention')
            ->assertSee('Plain-language deterministic answers are active');
    }

    public function test_unauthorized_staff_cannot_access_or_ask_the_assistant(): void
    {
        [, , $staff] = $this->account(UserRole::Staff);

        $this->actingAs($staff)->get('/ai')->assertForbidden();
        $this->actingAs($staff)->post('/ai/ask', ['question' => 'Show all sales'])->assertForbidden();
    }

    public function test_sales_intent_uses_authoritative_minor_unit_sales_facts(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $this->reportSale($company, $outlet, $admin, 'AI-SALE-1', '125.50');

        $answer = $this->ask($admin, 'How are sales today?');

        $this->assertSame('sales_summary', $answer['intent']);
        $this->assertSame(12550, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->assertSame('Completed POS sales only; CRM invoice reporting remains separate to prevent unlinked records being double counted.', $answer['coverage']);
    }

    public function test_tenant_data_never_appears_in_another_tenants_answer(): void
    {
        [$companyA, $outletA, $adminA] = $this->account();
        [$companyB, $outletB, $adminB] = $this->account();
        $this->reportSale($companyA, $outletA, $adminA, 'TENANT-A', '25.00');
        $this->reportSale($companyB, $outletB, $adminB, 'TENANT-B', '999.00');

        $answer = $this->ask($adminA, 'How are sales today?');

        $this->assertSame(2500, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->assertDatabaseMissing('ai_assistant_interactions', ['company_id' => $companyB->id, 'user_id' => $adminA->id]);
    }

    public function test_manager_answer_is_limited_to_assigned_outlet(): void
    {
        [$company, $outlet, $manager] = $this->account(UserRole::Manager);
        $other = $this->reportBranch($company, 'Hidden outlet');
        $admin = $this->reportUser($company, $outlet);
        $this->reportSale($company, $outlet, $manager, 'VISIBLE', '40.00');
        $this->reportSale($company, $other, $admin, 'HIDDEN', '90.00');

        $answer = $this->ask($manager, 'How are sales today?');

        $this->assertSame(4000, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->actingAs($manager)->post('/ai/ask', ['question' => 'Show sales', 'outlet_id' => $other->id])->assertForbidden();
    }

    public function test_administrator_can_select_an_authorized_outlet_without_combining_other_outlets(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $other = $this->reportBranch($company, 'Second outlet');
        $this->reportSale($company, $outlet, $admin, 'FIRST', '40.00');
        $this->reportSale($company, $other, $admin, 'SECOND', '90.00');

        $answer = $this->ask($admin, 'Show sales today', $other->id);

        $this->assertSame(9000, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->assertSame($other->id, AiAssistantInteraction::latest('id')->value('outlet_id'));
    }

    public function test_date_phrases_are_resolved_deterministically_in_company_timezone(): void
    {
        [$company, , $admin] = $this->account();
        $company->update(['timezone' => 'Asia/Kolkata']);
        $resolver = app(AiDateRangeResolver::class);

        $this->assertSame(['label' => 'Yesterday', 'date_from' => '2026-08-24', 'date_to' => '2026-08-24'], $resolver->resolve($admin->fresh(), 'sales yesterday'));
        $this->assertSame(['label' => 'Last month', 'date_from' => '2026-07-01', 'date_to' => '2026-07-31'], $resolver->resolve($admin->fresh(), 'compare last month'));
        $this->assertSame('2026-08-24', $resolver->resolve($admin->fresh(), 'this week')['date_from']);
    }

    public function test_intent_router_uses_only_approved_intents_and_reuses_immediate_context(): void
    {
        $router = app(AiIntentRouter::class);

        $this->assertSame('reorder', $router->route('What should I reorder?'));
        $this->assertSame('profitability', $router->route('Why is margin down?'));
        $this->assertSame('outlet_comparison', $router->route('Compare my outlets'));
        $this->assertSame('profitability', $router->route('Why?', 'profitability'));
    }

    public function test_arbitrary_sql_and_write_requests_are_blocked_as_advisory_only(): void
    {
        [, , $admin] = $this->account();

        foreach (['DROP TABLE users', 'Create a purchase order', 'Refund this invoice'] as $question) {
            $answer = $this->ask($admin, $question);
            $this->assertSame('advisory_only', $answer['intent']);
            $this->assertStringContainsString('read-only', $answer['summary']);
        }
    }

    public function test_provider_failure_falls_back_to_verified_deterministic_answer(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $this->reportSale($company, $outlet, $admin, 'FALLBACK', '60.00');
        $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
        {
            public function configured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'test-provider';
            }

            public function explain(array $draft, string $question): ?array
            {
                throw new RuntimeException('secret provider failure');
            }
        });

        $answer = $this->ask($admin, 'How are sales today?');

        $this->assertSame(6000, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->assertDatabaseHas('ai_assistant_interactions', ['user_id' => $admin->id, 'status' => 'provider_fallback', 'provider' => 'deterministic']);
    }

    public function test_provider_wording_is_sanitized_and_cannot_replace_authoritative_facts(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $this->reportSale($company, $outlet, $admin, 'SANITIZE', '72.25');
        $this->app->instance(AiProviderInterface::class, new class implements AiProviderInterface
        {
            public function configured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'safe-test';
            }

            public function explain(array $draft, string $question): ?array
            {
                return ['title' => '<script>alert(1)</script>Sales review', 'summary' => '<b>Helpful summary</b>', 'facts' => [['label' => 'Fake', 'value' => 999999]], 'recommendations' => ['<img src=x onerror=alert(1)>Review source data']];
            }
        });

        $answer = $this->ask($admin, 'How are sales today?');

        $this->assertStringNotContainsString('<script>', $answer['title']);
        $this->assertStringNotContainsString('<b>', $answer['summary']);
        $this->assertSame(7225, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->assertStringNotContainsString('<img', $answer['recommendations'][0]);
    }

    public function test_interaction_audit_stores_digest_not_raw_prompt_or_api_key(): void
    {
        [, , $admin] = $this->account();
        config(['ai.openai.api_key' => 'do-not-store-this-key']);
        $question = 'How are sales today for private customer context?';

        $this->ask($admin, $question);
        $record = AiAssistantInteraction::latest('id')->firstOrFail();

        $this->assertSame(hash('sha256', strtolower($question)), $record->prompt_digest);
        $this->assertStringNotContainsString($question, json_encode($record->getAttributes()));
        $this->assertStringNotContainsString('do-not-store-this-key', json_encode($record->getAttributes()));
    }

    public function test_conversation_context_is_scoped_to_the_authenticated_session_and_user(): void
    {
        [, , $admin] = $this->account();
        [, , $other] = $this->account();

        $this->actingAs($admin)->post('/ai/ask', ['question' => 'How much profit did we make?'])->assertRedirect();
        $this->actingAs($admin)->post('/ai/ask', ['question' => 'Why?'])->assertRedirect();
        $this->assertSame(['profitability'], AiAssistantInteraction::where('user_id', $admin->id)->pluck('intent')->unique()->values()->all());
        $this->assertDatabaseCount('ai_assistant_interactions', 2);

        $this->actingAs($other)->post('/ai/ask', ['question' => 'Why?'])->assertRedirect();
        $this->assertSame('business_summary', AiAssistantInteraction::where('user_id', $other->id)->value('intent'));
    }

    public function test_owner_command_center_brief_respects_ai_permission(): void
    {
        [, , $admin] = $this->account();
        [, , $sales] = $this->account(UserRole::Sales);

        $this->actingAs($admin)->get('/reports')->assertOk()->assertSee('AI Business Brief')->assertSee('Ask AI about this');
        $this->actingAs($sales)->get('/reports')->assertOk()->assertDontSee('AI Business Brief');
    }

    public function test_followups_and_source_drilldowns_render_without_provider(): void
    {
        [, , $admin] = $this->account();

        $this->actingAs($admin)->post('/ai/ask', ['question' => 'How are sales today?'])->assertRedirect();
        $this->actingAs($admin)->get('/ai')->assertOk()
            ->assertSee('Compare with last month')
            ->assertSee('Sales report')
            ->assertSee(route('reports.show', ['report' => 'sales']));
    }

    public function test_assistant_rate_limit_is_per_authenticated_tenant_user(): void
    {
        [, , $admin] = $this->account();
        config(['ai.requests_per_minute' => 1]);
        RateLimiter::clear('ai-assistant:'.$admin->company_id.':'.$admin->id);

        $this->actingAs($admin)->post('/ai/ask', ['question' => 'How are sales today?'])->assertRedirect();
        $this->actingAs($admin)->post('/ai/ask', ['question' => 'Show sales this month'])->assertTooManyRequests();
    }

    public function test_existing_financial_calculation_is_unchanged_by_ai_request(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $this->reportSale($company, $outlet, $admin, 'UNCHANGED', '19.99');
        $filters = ['outlet_id' => (string) $outlet->id, 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString()];
        $before = app(RetailReportingService::class)->summary($admin, $filters);

        $this->ask($admin, 'How are sales today?');
        $after = app(RetailReportingService::class)->summary($admin, $filters);

        $this->assertSame(1999, $before['metrics']['net_sales']);
        $this->assertSame($before['metrics'], $after['metrics']);
    }

    public function test_inventory_and_reorder_intents_use_deterministic_inventory_intelligence(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $warehouse = $this->reportWarehouse($company, $outlet, 'AI Warehouse');
        $product = $this->reportProduct($company, $outlet, 'Reorder product', '10.00');
        $this->reportStockLevel($company, $outlet, $warehouse, $product, '2.000', '5.000');

        $inventory = $this->ask($admin, 'What is my inventory value?');
        $reorder = $this->ask($admin, 'What should I reorder?');

        $this->assertSame('inventory', $inventory['intent']);
        $this->assertSame(2000, collect($inventory['facts'])->firstWhere('label', 'Stock value')['value']);
        $this->assertSame('reorder', $reorder['intent']);
        $this->assertStringContainsString('Reorder product', $reorder['facts'][0]['label']);
        $this->assertStringContainsString('advisory only', strtolower($reorder['coverage']));
    }

    public function test_profitability_intent_preserves_unavailable_cost_coverage_language(): void
    {
        [$company, $outlet, $admin] = $this->account();
        $sale = $this->reportSale($company, $outlet, $admin, 'NO-COST', '50.00');
        $product = $this->reportProduct($company, $outlet, 'Cost unavailable product');
        $this->reportSaleItem($sale, $product);

        $answer = $this->ask($admin, 'How much profit did we make?');

        $this->assertSame('profitability', $answer['intent']);
        $this->assertStringContainsString('not enough reliable cost evidence', strtolower($answer['summary']));
        $this->assertSame('Profitability report', $answer['sources'][0]['label']);
    }

    public function test_customer_and_crm_intents_use_tenant_scoped_persisted_records(): void
    {
        [$company, $outlet, $admin] = $this->account();
        Customer::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'customer_number' => 'AI-CUST', 'first_name' => 'Valued', 'display_name' => 'Valued Customer', 'status' => 'active', 'total_purchase_amount' => '300.00', 'is_active' => true]);
        $status = CrmLeadStatus::create(['company_id' => $company->id, 'name' => 'New', 'slug' => 'new', 'stage_type' => 'new', 'is_active' => true]);
        CrmLead::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'status_id' => $status->id, 'title' => 'Renewal conversation', 'business_name' => 'Follow Up Business', 'priority' => 'high', 'next_follow_up_at' => now()->subHour(), 'created_by' => $admin->id]);

        $customers = $this->ask($admin, 'Who are my best customers?');
        $followups = $this->ask($admin, 'Which leads need follow-up?');

        $this->assertSame('customer_insight', $customers['intent']);
        $this->assertSame('Valued Customer', $customers['facts'][0]['label']);
        $this->assertSame(30000, $customers['facts'][0]['value']);
        $this->assertSame('crm_followup', $followups['intent']);
        $this->assertSame('Follow Up Business', $followups['facts'][0]['label']);
    }

    public function test_new_tenant_receives_clear_empty_state_instead_of_invented_figures(): void
    {
        [, , $admin] = $this->account();

        $answer = $this->ask($admin, 'How are sales today?');

        $this->assertSame(0, collect($answer['facts'])->firstWhere('label', 'Net sales')['value']);
        $this->assertStringContainsString('no completed sales', strtolower($answer['summary']));
        $this->assertNotEmpty($answer['recommendations']);
    }

    private function ask(User $user, string $question, ?int $outletId = null): array
    {
        return app(AiAssistantService::class)->ask($user, $question, $outletId, null, fake()->uuid());
    }

    /** @return array{Company, Branch, User} */
    private function account(UserRole $role = UserRole::Administrator): array
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $outlet = $this->reportBranch($company, fake()->unique()->company().' Outlet');
        $user = $this->reportUser($company, $outlet, $role);
        if ($role !== UserRole::Administrator) {
            BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'user_id' => $user->id, 'assigned_by' => $user->id, 'is_default' => true, 'is_active' => true]);
        }

        return [$company, $outlet, $user];
    }
}
