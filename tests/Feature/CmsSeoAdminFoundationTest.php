<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsArticle;
use App\Models\Cms\CmsPage;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsSeoAdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_seo_pages_and_staff_cannot_access_them(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company);

        $this->actingAs($staff)->get('/cms/seo-pages')->assertForbidden();

        $this->actingAs($manager)
            ->post('/cms/seo-pages', $this->pagePayload())
            ->assertRedirect();

        $page = CmsPage::query()->where('company_id', $manager->company_id)->where('route_path', '/retail-pos')->firstOrFail();

        $this->assertSame(CmsPage::STATUS_DRAFT, $page->status);
        $this->assertSame('Retail POS Software', $page->seo->meta_title);
        $this->assertCount(1, $page->revisions);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.marketing_page.created', 'auditable_id' => $page->id]);
    }

    public function test_only_published_seo_pages_are_available_from_the_public_api(): void
    {
        $manager = $this->user(UserRole::Manager);
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);

        $this->actingAs($manager)->post('/cms/seo-pages', $this->pagePayload())->assertRedirect();
        $page = CmsPage::query()->where('company_id', $manager->company_id)->where('route_path', '/retail-pos')->firstOrFail();

        $this->getJson('/api/public/cms/seo-page?path=/retail-pos')->assertNotFound();

        $this->actingAs($manager)->post("/cms/seo-pages/{$page->id}/publish")->assertRedirect();

        $this->getJson('/api/public/cms/seo-page?path=/retail-pos')
            ->assertOk()
            ->assertJsonPath('data.route_path', '/retail-pos')
            ->assertJsonPath('data.seo.title', 'Retail POS Software')
            ->assertJsonMissingPath('data.company_id')
            ->assertJsonMissingPath('data.author_user_id');

        $this->actingAs($manager)->post("/cms/seo-pages/{$page->id}/unpublish")->assertRedirect();
        $this->getJson('/api/public/cms/seo-page?path=/retail-pos')->assertNotFound();
    }

    public function test_route_paths_are_unique_and_invalid_schema_json_is_rejected(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->from('/cms/seo-pages/create')
            ->post('/cms/seo-pages', $this->pagePayload(['schema_json' => '{not-json']))
            ->assertRedirect('/cms/seo-pages/create')
            ->assertSessionHasErrors('schema_json');

        $this->actingAs($manager)->post('/cms/seo-pages', $this->pagePayload())->assertRedirect();

        $this->actingAs($manager)
            ->from('/cms/seo-pages/create')
            ->post('/cms/seo-pages', $this->pagePayload(['title' => 'Duplicate route']))
            ->assertRedirect('/cms/seo-pages/create')
            ->assertSessionHasErrors('route_path');
    }

    public function test_landing_pages_and_articles_publish_to_the_public_read_api(): void
    {
        $manager = $this->user(UserRole::Manager);
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);

        $this->actingAs($manager)
            ->post('/cms/landing-pages', $this->pagePayload([
                'route_path' => '/solutions/retail',
                'title' => 'Retail Solution',
                'h1' => 'Retail solution',
                'page_type' => 'solution',
                'status' => CmsPage::STATUS_PUBLISHED,
                'content_sections' => '[{"type":"features","title":"Retail controls"}]',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('cms_pages', [
            'company_id' => $manager->company_id,
            'slug' => 'solutions-retail',
            'page_type' => 'solution',
            'status' => CmsPage::STATUS_PUBLISHED,
        ]);

        $this->getJson('/api/public/cms/landing-pages/solutions-retail')
            ->assertOk()
            ->assertJsonPath('data.title', 'Retail Solution')
            ->assertJsonPath('data.content_sections.0.type', 'features');

        $this->actingAs($manager)
            ->post('/cms/articles', [
                'title' => 'How retail teams use clear data',
                'slug' => 'clear-retail-data',
                'excerpt' => 'A practical retail operations article.',
                'content' => 'Published content for the public website.',
                'author_name' => 'RetailPOS Team',
                'category' => 'Operations',
                'tags' => '["retail","operations"]',
                'meta_title' => 'Retail data guide',
                'meta_description' => 'A clear retail data guide.',
                'status' => CmsArticle::STATUS_PUBLISHED,
                'include_in_sitemap' => '1',
                'sitemap_priority' => '0.6',
                'sitemap_changefreq' => 'monthly',
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post('/cms/articles', [
                'title' => 'Draft article',
                'slug' => 'draft-article',
                'status' => CmsArticle::STATUS_DRAFT,
                'include_in_sitemap' => '1',
            ])
            ->assertRedirect();

        $this->getJson('/api/public/cms/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'clear-retail-data')
            ->assertJsonMissingPath('data.0.content');

        $this->getJson('/api/public/cms/articles/clear-retail-data')
            ->assertOk()
            ->assertJsonPath('data.content', 'Published content for the public website.');
    }

    public function test_scheduled_page_requires_a_future_publish_time_and_keeps_public_api_private(): void
    {
        $manager = $this->user(UserRole::Manager);
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);

        $this->actingAs($manager)
            ->from('/cms/seo-pages/create')
            ->post('/cms/seo-pages', $this->pagePayload(['status' => CmsPage::STATUS_SCHEDULED]))
            ->assertRedirect('/cms/seo-pages/create')
            ->assertSessionHasErrors('scheduled_for');

        $this->actingAs($manager)
            ->post('/cms/seo-pages', $this->pagePayload([
                'status' => CmsPage::STATUS_SCHEDULED,
                'scheduled_for' => Carbon::now()->addDay()->format('Y-m-d H:i:s'),
            ]))
            ->assertRedirect();

        $page = CmsPage::query()->where('company_id', $manager->company_id)->where('route_path', '/retail-pos')->firstOrFail();
        $this->assertSame(CmsPage::STATUS_SCHEDULED, $page->status);
        $this->assertNotNull($page->scheduled_for);
        $this->getJson('/api/public/cms/seo-page?path=/retail-pos')->assertNotFound();
    }

    public function test_global_seo_settings_redirects_and_sitemap_are_company_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);

        $this->actingAs($manager)
            ->put('/cms/seo', [
                'default_meta_title' => 'RetailPOS',
                'default_meta_description' => 'Retail website defaults.',
                'default_canonical_url' => 'https://retailpos.biz',
                'company_name' => 'RetailPOS India',
                'contact_email' => 'hello@retailpos.test',
                'same_as_social_links' => '["https://linkedin.com/company/retailpos"]',
                'default_schema_organization' => '{"@type":"Organization","name":"RetailPOS"}',
                'robots_txt' => "User-agent: *\nAllow: /",
                'robots_default_index' => '1',
                'robots_default_follow' => '1',
                'sitemap_enabled' => '1',
                'sitemap_url' => 'https://retailpos.biz/sitemap.xml',
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post('/cms/redirects', ['source_url' => '/old-pos', 'target_url' => '/retail-pos', 'status_code' => 301, 'is_enabled' => '1', 'notes' => 'Migration redirect'])
            ->assertRedirect();

        $this->actingAs($manager)
            ->from('/cms/redirects')
            ->post('/cms/redirects', ['source_url' => 'old-pos', 'target_url' => '/retail-pos', 'status_code' => 308, 'is_enabled' => '1'])
            ->assertRedirect('/cms/redirects')
            ->assertSessionHasErrors(['source_url', 'status_code']);

        $this->actingAs($manager)
            ->post('/cms/seo-pages', $this->pagePayload(['status' => CmsPage::STATUS_PUBLISHED]))
            ->assertRedirect();

        $this->getJson('/api/public/cms/settings')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'RetailPOS India')
            ->assertJsonPath('data.contact_email', 'hello@retailpos.test');
        $this->getJson('/api/public/cms/redirects')->assertOk()->assertJsonPath('data.0.from_path', '/old-pos');
        $this->getJson('/api/public/cms/sitemap')->assertOk()->assertJsonPath('data.0.path', '/retail-pos');
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.redirect.created']);
    }

    public function test_cms_metrics_are_visible_on_the_command_center_dashboard_for_managers(): void
    {
        $manager = $this->user(UserRole::Manager);
        CmsPage::create([
            'company_id' => $manager->company_id,
            'author_user_id' => $manager->id,
            'slug' => 'dashboard-page',
            'route_path' => '/dashboard-page',
            'title' => 'Dashboard Page',
            'page_type' => 'seo',
            'status' => CmsPage::STATUS_PUBLISHED,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Website Publishing')
            ->assertSee('Published Pages');
    }

    /** @param array<string, mixed> $overrides */
    private function pagePayload(array $overrides = []): array
    {
        return array_merge([
            'route_path' => '/retail-pos',
            'title' => 'Retail POS Software',
            'h1' => 'Retail POS Software',
            'page_type' => 'seo',
            'meta_title' => 'Retail POS Software',
            'meta_description' => 'A clear retail POS solution.',
            'canonical_url' => 'https://retailpos.biz/retail-pos',
            'schema_json' => '{"@type":"WebPage"}',
            'robots_index' => '1',
            'robots_follow' => '1',
            'include_in_sitemap' => '1',
            'sitemap_priority' => '0.8',
            'sitemap_changefreq' => 'weekly',
            'status' => CmsPage::STATUS_DRAFT,
        ], $overrides);
    }

    private function user(UserRole $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();

        return User::factory()->for($company)->create(['role' => $role]);
    }
}
