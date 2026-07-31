<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLeadSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSourceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_default_is_optional_and_tenant_scoped(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post('/crm/settings/lead-sources', ['name' => 'Website', 'tone' => 'success'])->assertRedirect();

        $source = CrmLeadSource::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertFalse($source->is_default);
        $this->actingAs($manager)->get('/crm/leads/create')->assertOk()->assertSee('No source');
        $this->actingAs($manager)->post('/crm/settings/lead-sources', ['name' => 'Website', 'tone' => 'success'])->assertSessionHasErrors('name');
    }

    private function manager(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }
}
