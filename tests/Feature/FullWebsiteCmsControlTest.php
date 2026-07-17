<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsPage;
use App\Models\Company;
use App\Models\User;
use App\Services\Cms\WebsiteRevalidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FullWebsiteCmsControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_pages_and_case_studies_are_exposed_without_internal_ids(): void
    {
        $manager = $this->manager();
        config()->set('services.retailpos.public_lead_company_id', $manager->company_id);
        $page = CmsPage::create(['company_id' => $manager->company_id, 'author_user_id' => $manager->id, 'slug' => 'products', 'route_path' => '/products', 'title' => 'Products', 'page_type' => 'standard', 'status' => CmsPage::STATUS_PUBLISHED, 'is_active' => true, 'published_at' => now()]);
        CmsPage::create(['company_id' => $manager->company_id, 'author_user_id' => $manager->id, 'slug' => 'draft-page', 'route_path' => '/draft-page', 'title' => 'Draft page', 'page_type' => 'standard', 'status' => CmsPage::STATUS_DRAFT]);
        CmsCaseStudy::create(['company_id' => $manager->company_id, 'title' => 'Supermarket & Grocery Retail', 'slug' => 'supermarket-grocery', 'client_name' => '', 'status' => 'published', 'published_at' => now()]);
        CmsCaseStudy::create(['company_id' => $manager->company_id, 'title' => 'Newrie London', 'slug' => 'newrie-london', 'client_name' => '', 'status' => 'draft']);

        $this->getJson('/api/public/cms/pages')->assertOk()->assertJsonPath('data.0.slug', 'products');
        $this->getJson('/api/public/cms/pages/products')->assertOk()->assertJsonPath('data.slug', $page->slug)->assertJsonMissingPath('data.id');
        $this->getJson('/api/public/cms/case-studies')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'supermarket-grocery');
        $this->getJson('/api/public/cms/case-studies/newrie-london')->assertNotFound();
    }

    public function test_public_cms_lists_return_empty_json_when_no_public_company_is_configured(): void
    {
        config()->set('services.retailpos.public_lead_company_id', null);

        $this->getJson('/api/public/cms/pages')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/public/cms/navigation')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/public/cms/settings')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/public/cms/case-studies')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_website_routes_are_authorized_and_media_rejects_executables(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->get('/website/case-studies')->assertOk();
        $this->actingAs($manager)->get('/website/media')->assertOk();
        $this->actingAs($manager)->post('/website/media', ['file' => UploadedFile::fake()->create('dangerous.php', 12, 'application/x-httpd-php')])->assertSessionHasErrors('file');
        $this->actingAs($this->user(UserRole::Staff, $manager->company))->get('/website/case-studies')->assertForbidden();
    }

    public function test_revalidation_is_safe_without_configuration_and_posts_when_configured(): void
    {
        $service = app(WebsiteRevalidationService::class);
        $this->assertFalse($service->trigger('/products'));
        config()->set('services.retailpos.website_revalidate_url', 'https://website.test/revalidate');
        config()->set('services.retailpos.website_revalidate_token', 'secret');
        Http::fake(['https://website.test/revalidate' => Http::response([], 204)]);
        $this->assertTrue($service->trigger('/products'));
        Http::assertSent(fn ($request) => $request->url() === 'https://website.test/revalidate' && $request['path'] === '/products');
    }

    private function manager(): User { return $this->user(UserRole::Manager); }
    private function user(UserRole $role, ?Company $company = null): User { return User::factory()->for($company ?? Company::factory())->create(['role' => $role]); }
}
