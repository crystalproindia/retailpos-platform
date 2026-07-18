<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSetting;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CmsLivePublishingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_dashboard_surfaces_publishing_counts_and_health(): void
    {
        $manager = $this->manager();
        CmsPage::create($this->pageAttributes($manager, ['status' => CmsPage::STATUS_PUBLISHED, 'published_at' => now()]));
        CmsCaseStudy::create(['company_id' => $manager->company_id, 'title' => 'Draft client story', 'slug' => 'draft-client-story', 'client_name' => 'Client', 'status' => 'draft']);

        $this->actingAs($manager)
            ->get('/website')
            ->assertOk()
            ->assertSee('Website publishing health')
            ->assertSee('Published pages')
            ->assertSee('Draft case studies');
    }

    public function test_settings_preserve_unsubmitted_values_normalize_whatsapp_and_expose_only_public_values(): void
    {
        $manager = $this->manager();
        CmsSetting::create(['company_id' => $manager->company_id, 'group' => 'contact', 'key' => 'sales_email', 'label' => 'Sales Email', 'value' => 'sales@existing.test', 'value_type' => 'email', 'is_public' => true]);

        $this->actingAs($manager)
            ->put('/website/settings', [
                'india_whatsapp' => '+91 80726 82244',
                'global_email' => 'global@retailpos.biz',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('cms_settings', ['company_id' => $manager->company_id, 'key' => 'india_whatsapp', 'value' => '918072682244']);
        $this->assertDatabaseHas('cms_settings', ['company_id' => $manager->company_id, 'key' => 'sales_email', 'value' => 'sales@existing.test']);
        $this->assertDatabaseHas('cms_revalidation_logs', ['company_id' => $manager->company_id, 'status' => 'skipped_not_configured']);

        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);
        $this->getJson('/api/public/cms/settings')
            ->assertOk()
            ->assertJsonPath('data.website_settings.india_whatsapp', '918072682244')
            ->assertJsonMissingPath('data.website_settings.internal_only');
    }

    public function test_navigation_updates_feed_the_public_api_and_disabled_links_are_not_published(): void
    {
        $manager = $this->manager();
        $menu = CmsMenu::create(['company_id' => $manager->company_id, 'name' => 'Primary Header', 'location' => 'header', 'is_enabled' => true]);

        $this->actingAs($manager)
            ->post("/website/navigation/{$menu->id}/items", [
                'label' => 'Case Studies',
                'url' => '/case-studies',
                'opens_new_tab' => false,
                'is_enabled' => true,
                'sort_order' => 10,
            ])
            ->assertRedirect();

        $item = CmsMenuItem::query()->where('menu_id', $menu->id)->firstOrFail();
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);
        $this->getJson('/api/public/cms/navigation')->assertOk()->assertJsonPath('data.0.label', 'Case Studies');

        $this->actingAs($manager)
            ->from('/website/navigation')
            ->put("/website/navigation/{$menu->id}/items/{$item->id}", [
                'label' => 'Case Studies',
                'url' => '/case-studies',
                'opens_new_tab' => false,
                'is_enabled' => false,
                'sort_order' => 10,
            ])
            ->assertRedirect('/website/navigation');

        $this->assertFalse($item->fresh()->is_enabled);
        $this->getJson('/api/public/cms/navigation')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_case_studies_support_draft_publish_and_safe_json_validation(): void
    {
        $manager = $this->manager();
        $payload = $this->caseStudyPayload();

        $this->actingAs($manager)
            ->from('/website/case-studies/create')
            ->post('/website/case-studies', array_merge($payload, ['metrics_json' => '{not valid}']))
            ->assertRedirect('/website/case-studies/create')
            ->assertSessionHasErrors('metrics_json');

        $this->actingAs($manager)
            ->post('/website/case-studies', $payload)
            ->assertRedirect();

        $study = CmsCaseStudy::query()->where('company_id', $manager->company_id)->firstOrFail();
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);
        $this->getJson('/api/public/cms/case-studies')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($manager)->post("/website/case-studies/{$study->id}/publish")->assertRedirect();

        $this->getJson('/api/public/cms/case-studies')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'approved-client-story')
            ->assertJsonPath('data.0.title', 'Approved Client Story');
        $this->assertDatabaseHas('cms_revalidation_logs', ['company_id' => $manager->company_id, 'content_type' => 'case_study']);
    }

    public function test_invalid_section_json_is_rejected_and_published_page_refreshes_the_website(): void
    {
        $manager = $this->manager();
        $page = CmsPage::create($this->pageAttributes($manager));

        $this->actingAs($manager)
            ->from("/website/pages/{$page->id}/edit")
            ->post("/website/pages/{$page->id}/sections", [
                'section_key' => 'hero',
                'section_type' => 'hero',
                'settings' => '{invalid}',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect("/website/pages/{$page->id}/edit")
            ->assertSessionHasErrors('settings');

        config()->set('services.retailpos.website_revalidate_url', 'https://website.test/revalidate');
        config()->set('services.retailpos.website_revalidate_token', 'secret');
        Http::fake(['https://website.test/revalidate' => Http::response([], 204)]);

        $this->actingAs($manager)->post("/website/pages/{$page->id}/publish")->assertRedirect();

        $this->assertDatabaseHas('cms_revalidation_logs', [
            'company_id' => $manager->company_id,
            'path' => '/website-home',
            'status' => 'success',
            'response_code' => 204,
        ]);
        Http::assertSent(fn ($request) => $request['path'] === '/website-home' && $request['type'] === 'page' && $request['slug'] === 'website-home');
    }

    public function test_empty_public_cms_endpoints_are_safe_and_staff_cannot_manage_the_website(): void
    {
        $this->getJson('/api/public/cms/pages')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/public/cms/navigation')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/public/cms/settings')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/public/cms/case-studies')->assertOk()->assertExactJson(['data' => []]);

        $manager = $this->manager();
        $staff = User::factory()->for($manager->company)->create(['role' => UserRole::Staff]);
        $this->actingAs($staff)->get('/website')->assertForbidden();
    }

    private function manager(): User
    {
        return User::factory()->for(Company::factory())->create(['role' => UserRole::Manager]);
    }

    /** @return array<string, mixed> */
    private function pageAttributes(User $user, array $overrides = []): array
    {
        return $overrides + [
            'company_id' => $user->company_id,
            'author_user_id' => $user->id,
            'title' => 'Website Home',
            'slug' => 'website-home',
            'route_path' => '/website-home',
            'status' => CmsPage::STATUS_DRAFT,
            'is_active' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function caseStudyPayload(): array
    {
        return [
            'title' => 'Approved Client Story',
            'slug' => 'approved-client-story',
            'client_name' => 'Approved Client',
            'status' => 'draft',
            'metrics_json' => '{"stores":"25+"}',
            'schema_json' => '{"@context":"https://schema.org","@type":"CaseStudy"}',
        ];
    }
}
