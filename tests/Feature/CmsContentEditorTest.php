<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsContentPage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsContentEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_content_pages_and_sections_without_json_input(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)->get('/cms/content')
            ->assertOk()
            ->assertSee('Simple content editor')
            ->assertSee('Nothing here requires code knowledge.');

        $this->assertDatabaseHas('cms_content_pages', ['company_id' => $manager->company_id, 'page_key' => 'home', 'route_path' => '/']);
        $this->assertDatabaseCount('cms_content_sections', 9);

        $this->actingAs($manager)->post('/cms/content/pages', [
            'title' => 'Retail POS',
            'page_key' => 'retail-pos',
            'route_path' => '/products/retail-pos',
            'page_type' => 'product',
        ])->assertRedirect();

        $page = CmsContentPage::query()->where('company_id', $manager->company_id)->where('page_key', 'retail-pos')->firstOrFail();

        $this->actingAs($manager)->get("/cms/content/pages/{$page->id}")
            ->assertOk()
            ->assertSee('Content health')
            ->assertSee('Add new website content')
            ->assertSee('There is no code or JSON to manage here.');

        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/sections", $this->sectionPayload())
            ->assertRedirect();

        $section = $page->refresh()->sections()->firstOrFail();
        $this->actingAs($manager)->put("/cms/content/pages/{$page->id}/sections/{$section->id}", $this->sectionPayload([
            'title' => 'A clearer retail operation',
            'items' => [['title' => 'Live stock', 'description' => 'Keep every store informed.']],
        ]))->assertRedirect();

        $this->assertDatabaseHas('cms_content_sections', ['id' => $section->id, 'title' => 'A clearer retail operation']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.content_page.created', 'auditable_id' => $page->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.content_section.updated', 'auditable_id' => $section->id]);
    }

    public function test_staff_cannot_access_content_editor_and_paths_are_company_unique(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company);

        $this->actingAs($staff)->get('/cms/content')->assertForbidden();

        $this->actingAs($manager)->post('/cms/content/pages', [
            'title' => 'Contact', 'page_key' => 'contact', 'route_path' => '/contact', 'page_type' => 'contact',
        ])->assertRedirect();

        $this->actingAs($manager)->from('/cms/content')->post('/cms/content/pages', [
            'title' => 'Contact again', 'page_key' => 'contact-again', 'route_path' => '/contact', 'page_type' => 'contact',
        ])->assertRedirect('/cms/content')->assertSessionHasErrors('route_path');
    }

    public function test_public_content_api_only_returns_published_enabled_content(): void
    {
        $manager = $this->user(UserRole::Manager);
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);

        $this->actingAs($manager)->post('/cms/content/pages', [
            'title' => 'Retail operations', 'page_key' => 'retail-operations', 'route_path' => '/retail-operations', 'page_type' => 'solution',
        ])->assertRedirect();
        $page = CmsContentPage::query()->where('company_id', $manager->company_id)->where('page_key', 'retail-operations')->firstOrFail();

        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/sections", $this->sectionPayload([
            'section_key' => 'hero', 'title' => 'Operate with clarity', 'primary_cta_label' => 'Book a demo', 'primary_cta_url' => '/contact',
        ]))->assertRedirect();
        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/sections", $this->sectionPayload([
            'section_key' => 'hidden_feature', 'section_type' => 'feature_grid', 'is_enabled' => 0,
        ]))->assertRedirect();

        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/sections", $this->sectionPayload([
            'section_key' => 'benefits', 'section_type' => 'benefits', 'title' => 'Benefits',
        ]))->assertRedirect();
        $benefits = $page->refresh()->sections()->where('section_key', 'benefits')->firstOrFail();
        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/sections/{$benefits->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/sections/{$benefits->id}/move", ['direction' => 'up'])->assertRedirect();

        $this->getJson('/api/public/cms/content/page?path=/retail-operations')->assertNotFound();
        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/publish")->assertRedirect();

        $this->getJson('/api/public/cms/content/pages')
            ->assertOk()
            ->assertJsonPath('data.0.page_key', 'retail-operations');
        $this->getJson('/api/public/cms/content/page?path=/retail-operations')
            ->assertOk()
            ->assertJsonPath('data.page_key', 'retail-operations')
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.section_key', 'benefits')
            ->assertJsonMissingPath('data.company_id')
            ->assertJsonMissingPath('data.sections.0.id');
        $this->getJson('/api/public/cms/content/page/retail-operations')->assertOk()->assertJsonPath('data.sections.1.title', 'Operate with clarity');

        $this->actingAs($manager)->post("/cms/content/pages/{$page->id}/unpublish")->assertRedirect();
        $this->getJson('/api/public/cms/content/page?path=/retail-operations')->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.content_section.reordered', 'auditable_id' => $benefits->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.content_page.unpublished', 'auditable_id' => $page->id]);
    }

    public function test_navigation_footer_and_dashboard_are_tenant_scoped_and_public_safe(): void
    {
        $manager = $this->user(UserRole::Manager);
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);

        $this->actingAs($manager)->post('/cms/content/navigation', [
            'label' => 'Pricing', 'url' => '/pricing', 'location' => 'header', 'sort_order' => 5, 'is_enabled' => 1, 'opens_new_tab' => 0,
        ])->assertRedirect();
        $this->actingAs($manager)->post('/cms/content/navigation', [
            'label' => 'Hidden', 'url' => '/internal', 'location' => 'header', 'sort_order' => 10, 'is_enabled' => 0, 'opens_new_tab' => 0,
        ])->assertRedirect();
        $this->actingAs($manager)->from('/cms/content/navigation')->post('/cms/content/navigation', [
            'label' => 'Unsafe', 'url' => 'javascript:alert(1)', 'location' => 'header', 'is_enabled' => 1, 'opens_new_tab' => 0,
        ])->assertRedirect('/cms/content/navigation')->assertSessionHasErrors('url');
        $this->actingAs($manager)->post('/cms/content/footer', [
            'block_key' => 'company_description', 'title' => 'RetailPOS', 'content' => 'Retail operations software.',
            'links' => [['label' => 'Privacy', 'url' => '/privacy']], 'sort_order' => 5, 'is_enabled' => 1,
        ])->assertRedirect();

        $this->getJson('/api/public/cms/content/navigation')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Pricing')->assertJsonMissingPath('data.0.id');
        $this->getJson('/api/public/cms/content/footer')
            ->assertOk()->assertJsonPath('data.0.links.0.url', '/privacy')->assertJsonMissingPath('data.0.company_id');
        $this->actingAs($manager)->get('/cms')->assertOk()->assertSee('Content editor');
    }

    /** @param array<string, mixed> $overrides */
    private function sectionPayload(array $overrides = []): array
    {
        return array_merge([
            'section_key' => 'hero',
            'section_type' => 'hero',
            'title' => 'A better website start',
            'subtitle' => 'Useful website content.',
            'eyebrow' => 'RetailPOS',
            'body' => 'Use this section to introduce the page.',
            'image_url' => 'https://example.test/hero.jpg',
            'primary_cta_label' => 'Get started',
            'primary_cta_url' => '/contact',
            'secondary_cta_label' => 'See pricing',
            'secondary_cta_url' => '/pricing',
            'items' => [],
            'is_enabled' => 1,
        ], $overrides);
    }

    private function user(UserRole $role, ?Company $company = null): User
    {
        $company ??= Company::factory()->create();

        return User::factory()->for($company)->create(['role' => $role]);
    }
}
