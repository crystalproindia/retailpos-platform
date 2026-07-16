<?php

namespace Tests\Feature;

use App\Enums\Crm\DemoScheduleStatus;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadScoreCategory;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\DemoSchedule;
use App\Models\User;
use App\Services\Crm\CrmLeadScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAiLeadAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_based_scoring_promotes_active_commercial_leads_and_projects_to_dashboard_and_pipeline(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['expected_value' => 150000, 'next_follow_up_at' => now()->addDay()]);
        DemoSchedule::create([
            'company_id' => $manager->company_id,
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'scheduled_by' => $manager->id,
            'title' => 'Demo: '.$lead->title,
            'scheduled_date' => today(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'timezone' => config('app.timezone'),
            'meeting_mode' => 'google_meet_later',
            'status' => DemoScheduleStatus::Scheduled,
        ]);
        CrmQuotation::create([
            'company_id' => $manager->company_id,
            'lead_id' => $lead->id,
            'quotation_number' => 'RPQ-'.now()->format('Y').'-000001',
            'title' => 'RetailPOS proposal',
            'currency' => 'INR',
            'grand_total' => 150000,
            'status' => QuotationStatus::Sent,
            'created_by' => $manager->id,
        ]);
        CrmActivity::create([
            'company_id' => $manager->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $manager->id,
            'created_by' => $manager->id,
            'type' => ActivityType::Call,
            'subject' => 'Qualification call completed',
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => LeadPriority::High,
        ]);

        $score = app(CrmLeadScoringService::class)->refresh($lead, $manager);

        $this->assertSame(LeadScoreCategory::Hot, $score->category);
        $this->assertDatabaseHas('crm_lead_scores', ['lead_id' => $lead->id, 'category' => LeadScoreCategory::Hot->value]);
        $this->actingAs($manager)->get('/crm')->assertOk()->assertSee('AI lead insights')->assertSee($lead->title);
        $this->actingAs($manager)->get('/crm/pipeline?ai_category=hot&view=list')->assertOk()->assertSee($lead->title)->assertSee('AI score');
    }

    public function test_manual_analysis_and_follow_up_drafts_are_audited_without_sending_or_storing_message_body(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);

        $this->actingAs($manager)->post("/crm/leads/{$lead->id}/ai/analyze")
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead_score.refreshed', 'auditable_id' => $lead->id]);
        $this->assertTrue(CrmActivity::query()->where('crm_lead_id', $lead->id)->where('subject', 'like', 'AI lead score refreshed:%')->exists());

        $this->actingAs($manager)->post("/crm/leads/{$lead->id}/ai/follow-up", [
            'message_type' => 'proposal_follow_up',
            'tone' => 'professional',
            'length' => 'normal',
        ])->assertRedirect()->assertSessionHas('aiFollowUp');

        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.follow_up.generated', 'auditable_id' => $lead->id]);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'AI follow-up draft generated']);
        $this->assertDatabaseMissing('crm_activities', ['description' => 'Hi Asha Mehta, I wanted to check whether the RetailPOS proposal raised any questions for Retail Group. We can clarify scope, rollout, or commercial details.']);
    }

    public function test_staff_cannot_access_ai_actions_and_stale_command_refreshes_missing_scores(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $lead = $this->lead($manager);

        $this->actingAs($staff)->post("/crm/leads/{$lead->id}/ai/analyze")->assertForbidden();
        $this->actingAs($staff)->post("/crm/leads/{$lead->id}/ai/follow-up", [
            'message_type' => 'whatsapp_follow_up', 'tone' => 'professional', 'length' => 'short',
        ])->assertForbidden();

        $this->artisan('retailpos:crm-refresh-lead-scores --stale')
            ->expectsOutput('Refreshed 1 CRM lead score(s).')
            ->assertSuccessful();
        $this->assertDatabaseHas('crm_lead_scores', ['lead_id' => $lead->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function lead(User $user, array $overrides = []): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'website-contact'], ['name' => 'Website Contact', 'is_active' => true]);
        $status = CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'sort_order' => 1]);

        return CrmLead::create(array_merge([
            'company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'source_id' => $source->id, 'status_id' => $status->id,
            'assigned_user_id' => $user->id, 'created_by' => $user->id, 'title' => 'AI Retail Opportunity', 'business_name' => 'Retail Group',
            'contact_name' => 'Asha Mehta', 'email' => 'asha@example.test', 'phone' => '+91 90000 11111', 'currency' => 'INR',
            'expected_value' => 40000, 'priority' => LeadPriority::Medium,
        ], $overrides));
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
