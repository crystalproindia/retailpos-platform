<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('route_path', 500)->nullable()->after('slug');
            $table->foreignId('updated_by')->nullable()->after('author_user_id')->constrained('users')->nullOnDelete();
            $table->string('h1')->nullable()->after('title');
            $table->longText('intro_content')->nullable()->after('hero_content');
            $table->longText('footer_seo_content')->nullable()->after('body_content');
            $table->string('primary_cta_label')->nullable()->after('cta_url');
            $table->string('primary_cta_url')->nullable()->after('primary_cta_label');
            $table->string('secondary_cta_label')->nullable()->after('primary_cta_url');
            $table->string('secondary_cta_url')->nullable()->after('secondary_cta_label');
            $table->json('content_sections')->nullable()->after('secondary_cta_url');
            $table->json('faq_items')->nullable()->after('content_sections');
            $table->json('related_product_keys')->nullable()->after('faq_items');
            $table->json('related_industry_keys')->nullable()->after('related_product_keys');
            $table->boolean('robots_index')->default(true)->after('is_active');
            $table->boolean('robots_follow')->default(true)->after('robots_index');
            $table->longText('schema_json')->nullable()->after('robots_follow');
            $table->boolean('include_in_sitemap')->default(true)->after('schema_json');
            $table->decimal('sitemap_priority', 2, 1)->default(0.5)->after('include_in_sitemap');
            $table->string('sitemap_changefreq', 20)->default('weekly')->after('sitemap_priority');
            $table->unique(['company_id', 'route_path'], 'cms_pages_company_route_uq');
            $table->index(['company_id', 'page_type', 'status'], 'cms_pages_company_type_status_ix');
        });

        Schema::table('cms_seo_settings', function (Blueprint $table): void {
            $table->foreignId('default_twitter_image_id')->nullable()->after('default_og_image_id')->constrained('cms_media')->nullOnDelete();
            $table->string('company_name')->nullable()->after('default_meta_title');
            $table->string('company_logo_url')->nullable()->after('company_name');
            $table->string('contact_phone_india')->nullable()->after('company_logo_url');
            $table->string('contact_phone_singapore')->nullable()->after('contact_phone_india');
            $table->string('contact_phone_malaysia')->nullable()->after('contact_phone_singapore');
            $table->string('contact_email')->nullable()->after('contact_phone_malaysia');
            $table->text('address')->nullable()->after('contact_email');
            $table->json('same_as_social_links')->nullable()->after('address');
            $table->longText('default_schema_organization')->nullable()->after('schema_markup');
            $table->boolean('robots_default_index')->default(true)->after('robots_txt');
            $table->boolean('robots_default_follow')->default(true)->after('robots_default_index');
            $table->string('sitemap_url')->nullable()->after('sitemap_enabled');
        });

        Schema::table('cms_redirects', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('is_enabled');
            $table->timestamp('last_hit_at')->nullable()->after('hit_count');
            $table->index(['company_id', 'is_enabled'], 'cms_redirect_company_active_ix');
        });
    }

    public function down(): void
    {
        Schema::table('cms_redirects', function (Blueprint $table): void {
            $table->dropIndex('cms_redirect_company_active_ix');
            $table->dropColumn(['notes', 'last_hit_at']);
        });
        Schema::table('cms_seo_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_twitter_image_id');
            $table->dropColumn(['company_name', 'company_logo_url', 'contact_phone_india', 'contact_phone_singapore', 'contact_phone_malaysia', 'contact_email', 'address', 'same_as_social_links', 'default_schema_organization', 'robots_default_index', 'robots_default_follow', 'sitemap_url']);
        });
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->dropUnique('cms_pages_company_route_uq');
            $table->dropIndex('cms_pages_company_type_status_ix');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['route_path', 'h1', 'intro_content', 'footer_seo_content', 'primary_cta_label', 'primary_cta_url', 'secondary_cta_label', 'secondary_cta_url', 'content_sections', 'faq_items', 'related_product_keys', 'related_industry_keys', 'robots_index', 'robots_follow', 'schema_json', 'include_in_sitemap', 'sitemap_priority', 'sitemap_changefreq']);
        });
    }
};
