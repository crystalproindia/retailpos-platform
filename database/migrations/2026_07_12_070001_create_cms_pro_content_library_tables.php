<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table): void {
            $table->string('page_type')->default('standard')->after('title');
            $table->string('cta_label')->nullable()->after('body_content');
            $table->string('cta_url')->nullable()->after('cta_label');
            $table->boolean('is_active')->default(true)->after('status');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->index(['company_id', 'page_type', 'status']);
        });

        Schema::table('cms_homepage_sections', function (Blueprint $table): void {
            $table->string('eyebrow')->nullable()->after('name');
            $table->string('background_style')->nullable()->after('cta_url');
            $table->string('layout_style')->nullable()->after('background_style');
        });

        Schema::table('cms_menu_items', function (Blueprint $table): void {
            $table->string('badge_text')->nullable()->after('icon');
            $table->text('description')->nullable()->after('badge_text');
        });

        Schema::table('cms_footer_profiles', function (Blueprint $table): void {
            $table->foreignId('footer_logo_media_id')->nullable()->after('company_id')->constrained('cms_media')->nullOnDelete();
            $table->text('india_contact')->nullable()->after('address');
            $table->text('singapore_contact')->nullable()->after('india_contact');
            $table->text('malaysia_contact')->nullable()->after('singapore_contact');
            $table->text('bahrain_contact')->nullable()->after('malaysia_contact');
        });

        Schema::create('cms_client_logos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logo_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('name');
            $table->string('website_url')->nullable();
            $table->string('industry')->nullable();
            $table->string('location')->nullable();
            $table->text('short_description')->nullable();
            $table->string('display_style')->default('color');
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_on_homepage')->default(true);
            $table->boolean('show_on_case_studies')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('cms_case_studies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_logo_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->foreignId('featured_image_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->foreignId('og_image_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('client_name');
            $table->string('industry')->nullable();
            $table->string('location')->nullable();
            $table->string('project_type')->nullable();
            $table->text('short_summary')->nullable();
            $table->longText('challenge')->nullable();
            $table->longText('solution')->nullable();
            $table->longText('key_features')->nullable();
            $table->longText('results')->nullable();
            $table->json('metrics')->nullable();
            $table->text('testimonial_quote')->nullable();
            $table->json('gallery_media_ids')->nullable();
            $table->string('related_product')->nullable();
            $table->string('related_module')->nullable();
            $table->string('related_industry')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_link')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status', 'is_featured']);
        });

        Schema::create('cms_case_study_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('case_study_id')->constrained('cms_case_studies')->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->string('section_type');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->longText('content')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['case_study_id', 'sort_order']);
        });

        Schema::create('cms_testimonials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logo_or_photo_media_id')->nullable()->constrained('cms_media')->nullOnDelete();
            $table->foreignId('case_study_id')->nullable()->constrained('cms_case_studies')->nullOnDelete();
            $table->string('client_name');
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->longText('testimonial_text');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('industry')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_on_homepage')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('cms_trust_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('show_on_homepage')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('cms_cta_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('button_text');
            $table->string('button_link');
            $table->string('secondary_button_text')->nullable();
            $table->string('secondary_button_link')->nullable();
            $table->string('location')->nullable();
            $table->string('style')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active', 'location']);
        });

        Schema::create('cms_theme_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('background_color')->nullable();
            $table->string('text_color')->nullable();
            $table->string('button_color')->nullable();
            $table->string('button_radius_style')->nullable();
            $table->string('card_radius_style')->nullable();
            $table->string('website_theme_mode')->default('clean_light');
            $table->string('header_style')->nullable();
            $table->string('footer_style')->nullable();
            $table->string('cta_button_style')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique('company_id');
        });

        Schema::create('cms_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->longText('answer');
            $table->string('category')->nullable();
            $table->string('page_location')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_faqs');
        Schema::dropIfExists('cms_theme_settings');
        Schema::dropIfExists('cms_cta_blocks');
        Schema::dropIfExists('cms_trust_metrics');
        Schema::dropIfExists('cms_testimonials');
        Schema::dropIfExists('cms_case_study_sections');
        Schema::dropIfExists('cms_case_studies');
        Schema::dropIfExists('cms_client_logos');
        Schema::table('cms_footer_profiles', fn (Blueprint $table) => $table->dropConstrainedForeignId('footer_logo_media_id'));
        Schema::table('cms_footer_profiles', fn (Blueprint $table) => $table->dropColumn(['india_contact', 'singapore_contact', 'malaysia_contact', 'bahrain_contact']));
        Schema::table('cms_menu_items', fn (Blueprint $table) => $table->dropColumn(['badge_text', 'description']));
        Schema::table('cms_homepage_sections', fn (Blueprint $table) => $table->dropColumn(['eyebrow', 'background_style', 'layout_style']));
        Schema::table('cms_pages', fn (Blueprint $table) => $table->dropColumn(['page_type', 'cta_label', 'cta_url', 'is_active', 'sort_order']));
    }
};
