<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_content_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('page_key', 120);
            $table->string('route_path', 500)->nullable();
            $table->string('page_type', 40);
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'page_key'], 'cms_cont_pg_company_key_uq');
            $table->unique(['company_id', 'route_path'], 'cms_cont_pg_company_path_uq');
            $table->index(['company_id', 'status'], 'cms_cont_pg_company_status_ix');
            $table->index(['company_id', 'page_type'], 'cms_cont_pg_company_type_ix');
        });

        Schema::create('cms_content_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_page_id')->constrained('cms_content_pages')->cascadeOnDelete();
            $table->string('section_key', 120);
            $table->string('section_type', 40);
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('eyebrow')->nullable();
            $table->text('body')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->string('primary_cta_label')->nullable();
            $table->string('primary_cta_url', 1000)->nullable();
            $table->string('secondary_cta_label')->nullable();
            $table->string('secondary_cta_url', 1000)->nullable();
            $table->json('items')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['content_page_id', 'section_key'], 'cms_cont_sec_page_key_uq');
            $table->index(['content_page_id', 'sort_order'], 'cms_cont_sec_page_sort_ix');
            $table->index(['content_page_id', 'is_enabled'], 'cms_cont_sec_page_enabled_ix');
        });

        Schema::create('cms_navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 1000);
            $table->foreignId('parent_id')->nullable()->constrained('cms_navigation_items')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('location', 20);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('opens_new_tab')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'location', 'sort_order'], 'cms_nav_company_location_sort_ix');
            $table->index(['parent_id', 'sort_order'], 'cms_nav_parent_sort_ix');
        });

        Schema::create('cms_footer_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('block_key', 120);
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('links')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'block_key'], 'cms_footer_company_key_uq');
            $table->index(['company_id', 'is_enabled', 'sort_order'], 'cms_footer_company_enabled_sort_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_footer_blocks');
        Schema::dropIfExists('cms_navigation_items');
        Schema::dropIfExists('cms_content_sections');
        Schema::dropIfExists('cms_content_pages');
    }
};
