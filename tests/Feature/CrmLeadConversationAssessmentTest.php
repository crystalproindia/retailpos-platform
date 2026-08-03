<?php

namespace Tests\Feature;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\DomainEventLog;
use App\Models\User;
use App\Repositories\Crm\ActivityRepository;
use App\Support\Crm\LeadConversationAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmLeadConversationAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_creation_accepts_blank_optional_fields_and_retry_token_prevents_duplicate_records(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $token = (string) Str::uuid();
        $payload = $this->leadPayload($fixtures, [
            'title' => 'Retry-safe lead',
            'lead_creation_token' => $token,
            'source_id' => '',
            'assigned_user_id' => '',
            'email' => '',
            'phone' => '',
            'expected_value' => '',
            'next_follow_up_at' => '',
            'last_contacted_at' => '',
        ]);

        $this->actingAs($manager)->post('/crm/leads', $payload)->assertRedirect();
        $this->actingAs($manager)->post('/crm/leads', $payload)->assertRedirect();

        $lead = CrmLead::query()->where('creation_token', $token)->firstOrFail();

        $this->assertDatabaseCount('crm_leads', 1);
        $this->assertNull($lead->source_id);
        $this->assertSame($manager->id, $lead->assigned_user_id);
        $this->assertDatabaseCount('crm_activities', 1);
        $this->assertSame(1, DomainEventLog::query()->where('event_key', 'crm.lead.created')->count());
    }

    public function test_lead_creation_with_ratings_calculates_a_transparent_assessment_and_renders_crm_surfaces(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);

        $this->actingAs($manager)
            ->post('/crm/leads', $this->leadPayload($fixtures, [
                'title' => 'Engaged retail prospect',
                'client_receptiveness_rating' => 5,
                'buying_interest_rating' => 4,
                'follow_up_urgency_rating' => 4,
            ]))
            ->assertRedirect();

        $lead = CrmLead::query()->where('title', 'Engaged retail prospect')->firstOrFail();
        $assessment = $lead->conversationAssessment();

        $this->assertSame(4.3, $assessment->average);
        $this->assertSame('Hot Lead', $assessment->qualification);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'crm.lead.conversation_assessment_updated',
            'auditable_id' => $lead->id,
        ]);

        $this->actingAs($manager)->get('/crm/leads')->assertOk()->assertSee('Hot Lead')->assertSee('4.3/5');
        $this->actingAs($manager)->get("/crm/leads/{$lead->id}")->assertOk()->assertSee('Conversation Assessment')->assertSee('Staff-entered conversation assessment');
        $this->actingAs($manager)->get("/crm/leads/{$lead->id}/edit")->assertOk()->assertSee('Client receptiveness')->assertSee('Buying interest')->assertSee('Follow-up urgency');
    }

    public function test_rating_validation_rejects_out_of_range_and_decimal_values_with_field_errors(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);

        foreach ([
            'client_receptiveness_rating' => 0,
            'buying_interest_rating' => 6,
            'follow_up_urgency_rating' => '3.5',
        ] as $field => $value) {
            $this->actingAs($manager)
                ->from('/crm/leads/create')
                ->post('/crm/leads', $this->leadPayload($fixtures, [$field => $value]))
                ->assertRedirect('/crm/leads/create')
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_invalid_priority_and_cross_company_source_return_readable_field_errors(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $otherManager = $this->user(UserRole::Manager);
        $otherSource = $this->fixtures($otherManager)['source'];

        $this->actingAs($manager)
            ->from('/crm/leads/create')
            ->post('/crm/leads', $this->leadPayload($fixtures, [
                'priority' => 'invalid-priority',
                'source_id' => $otherSource->id,
            ]))
            ->assertRedirect('/crm/leads/create')
            ->assertSessionHasErrors(['priority', 'source_id']);

        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_existing_lead_assessment_can_be_updated_or_cleared_by_authorized_users_only(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures, [
            'client_receptiveness_rating' => 3,
            'buying_interest_rating' => 3,
            'follow_up_urgency_rating' => 3,
        ]);

        $this->actingAs($staff)
            ->put("/crm/leads/{$lead->id}", $this->leadPayload($fixtures, ['title' => $lead->title]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put("/crm/leads/{$lead->id}", $this->leadPayload($fixtures, [
                'title' => $lead->title,
                'client_receptiveness_rating' => '',
                'buying_interest_rating' => '',
                'follow_up_urgency_rating' => '',
            ]))
            ->assertRedirect();

        $assessment = $lead->refresh()->conversationAssessment();
        $this->assertNull($assessment->average);
        $this->assertSame('Not Rated', $assessment->qualification);
    }

    public function test_qualification_filters_and_follow_up_queue_use_the_authorized_lead_assessment(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $hot = $this->lead($manager, $fixtures, [
            'title' => 'Hot assessment',
            'client_receptiveness_rating' => 5,
            'buying_interest_rating' => 4,
            'follow_up_urgency_rating' => 5,
            'last_contacted_at' => now()->subDays(2),
        ]);
        $warm = $this->lead($manager, $fixtures, [
            'title' => 'Warm assessment',
            'client_receptiveness_rating' => 3,
            'buying_interest_rating' => 3,
            'follow_up_urgency_rating' => 3,
        ]);
        $notRated = $this->lead($manager, $fixtures, ['title' => 'No assessment']);

        foreach ([$hot, $warm] as $lead) {
            CrmActivity::create([
                'company_id' => $manager->company_id,
                'crm_lead_id' => $lead->id,
                'assigned_user_id' => $manager->id,
                'created_by' => $manager->id,
                'type' => ActivityType::FollowUp->value,
                'subject' => 'Call '.$lead->title,
                'scheduled_at' => now()->subHour(),
                'priority' => LeadPriority::High->value,
            ]);
        }

        $this->actingAs($manager)
            ->get('/crm/leads?qualification=hot&high_urgency=1')
            ->assertOk()
            ->assertSee('Hot assessment')
            ->assertDontSee('Warm assessment')
            ->assertDontSee('No assessment');

        $activities = app(ActivityRepository::class)->followUpsForUser($manager);
        $this->assertSame($hot->id, $activities->firstOrFail()->crm_lead_id);
        $this->actingAs($manager)->get('/crm/follow-ups')->assertOk()->assertSee('Hot Lead')->assertSee('Urgency 5/5');
    }

    public function test_assessment_boundaries_are_not_a_replacement_for_lead_status_or_priority(): void
    {
        $this->assertSame('Not Rated', LeadConversationAssessment::fromRatings(null, null, null)->qualification);
        $this->assertSame('Cold Lead', LeadConversationAssessment::fromRatings(1, 2, 4)->qualification);
        $this->assertSame('Warm Lead', LeadConversationAssessment::fromRatings(2, 3, 3)->qualification);
        $this->assertSame('Hot Lead', LeadConversationAssessment::fromRatings(4, 4, 4)->qualification);
    }

    public function test_cross_tenant_access_is_denied_for_private_assessment_data(): void
    {
        $manager = $this->user(UserRole::Manager);
        $otherManager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures, ['client_receptiveness_rating' => 5]);

        $this->actingAs($otherManager)->get("/crm/leads/{$lead->id}")->assertNotFound();
    }

    /** @return array{source: CrmLeadSource, status: CrmLeadStatus} */
    private function fixtures(User $user): array
    {
        return [
            'source' => CrmLeadSource::create([
                'company_id' => $user->company_id,
                'name' => 'Manual Entry',
                'slug' => 'manual-entry',
                'is_active' => true,
                'is_default' => true,
            ]),
            'status' => CrmLeadStatus::create([
                'company_id' => $user->company_id,
                'name' => 'New',
                'slug' => 'new',
                'stage_type' => LeadStageType::New->value,
                'probability' => 0,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ]),
        ];
    }

    /** @param array{source: CrmLeadSource, status: CrmLeadStatus} $fixtures
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function leadPayload(array $fixtures, array $overrides = []): array
    {
        return array_merge([
            'source_id' => $fixtures['source']->id,
            'status_id' => $fixtures['status']->id,
            'title' => 'CRM conversation lead',
            'business_name' => 'Assessment Retail',
            'contact_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'expected_value' => 125000,
            'currency' => 'INR',
            'priority' => LeadPriority::Medium->value,
        ], $overrides);
    }

    /** @param array{source: CrmLeadSource, status: CrmLeadStatus} $fixtures
     * @param array<string, mixed> $overrides
     */
    private function lead(User $user, array $fixtures, array $overrides = []): CrmLead
    {
        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $fixtures['source']->id,
            'status_id' => $fixtures['status']->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Existing assessment lead',
            'priority' => LeadPriority::Medium->value,
            'currency' => 'INR',
        ], $overrides));
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
    }
}
