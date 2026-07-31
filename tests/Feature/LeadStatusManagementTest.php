<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_default_is_tenant_scoped_and_duplicate_names_are_rejected(): void
    {
        $manager = $this->manager();
        $other = $this->manager();

        $this->actingAs($manager)->post('/crm/settings/lead-statuses', $this->payload('New', true))->assertRedirect();
        $this->actingAs($manager)->post('/crm/settings/lead-statuses', $this->payload('NEW', false))->assertSessionHasErrors('name');
        $this->actingAs($other)->post('/crm/settings/lead-statuses', $this->payload('New', true))->assertRedirect();

        $this->assertSame(1, CrmLeadStatus::query()->where('company_id', $manager->company_id)->where('is_default', true)->count());
        $this->assertSame(1, CrmLeadStatus::query()->where('company_id', $other->company_id)->where('is_default', true)->count());
    }

    private function manager(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    /** @return array<string, mixed> */
    private function payload(string $name, bool $default): array
    {
        return ['name' => $name, 'stage_type' => LeadStageType::New->value, 'tone' => 'neutral', 'probability' => 10, 'is_default' => $default];
    }
}
