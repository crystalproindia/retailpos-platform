<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_industries', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label', 120);
            $table->string('icon', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('account_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('destination', 255);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('resend_available_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'channel', 'consumed_at'], 'account_verification_lookup_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('mobile', 32)->nullable()->unique()->after('email');
            // Existing accounts are trusted during this additive rollout. New
            // platform-provisioned accounts explicitly begin as pending.
            $table->string('verification_status', 24)->default('verified')->after('is_platform_admin');
            $table->timestamp('verification_completed_at')->nullable()->after('verification_status');
            $table->boolean('requires_password_change')->default(false)->after('verification_completed_at');
        });

        DB::table('saas_industries')->insert(array_map(
            fn (array $industry): array => $industry + ['created_at' => now(), 'updated_at' => now()],
            [
                ['key' => 'fashion_apparel', 'label' => 'Fashion & Apparel', 'icon' => 'tag', 'sort_order' => 10, 'is_enabled' => true, 'description' => 'Clothing, fashion, and apparel stores.'],
                ['key' => 'grocery_supermarket', 'label' => 'Grocery & Supermarket', 'icon' => 'package', 'sort_order' => 20, 'is_enabled' => true, 'description' => 'Grocery, supermarket, and daily-needs retail.'],
                ['key' => 'electronics', 'label' => 'Electronics', 'icon' => 'products', 'sort_order' => 30, 'is_enabled' => true, 'description' => 'Consumer electronics and appliances.'],
                ['key' => 'mobile_accessories', 'label' => 'Mobile & Accessories', 'icon' => 'products', 'sort_order' => 40, 'is_enabled' => true, 'description' => 'Mobile phones, repairs, and accessories.'],
                ['key' => 'pharmacy', 'label' => 'Pharmacy', 'icon' => 'health', 'sort_order' => 50, 'is_enabled' => true, 'description' => 'Pharmacy and healthcare retail.'],
                ['key' => 'jewellery', 'label' => 'Jewellery', 'icon' => 'tag', 'sort_order' => 60, 'is_enabled' => true, 'description' => 'Jewellery and precious goods.'],
                ['key' => 'footwear', 'label' => 'Footwear', 'icon' => 'products', 'sort_order' => 70, 'is_enabled' => true, 'description' => 'Footwear and fashion accessories.'],
                ['key' => 'furniture_home', 'label' => 'Furniture & Home', 'icon' => 'company', 'sort_order' => 80, 'is_enabled' => true, 'description' => 'Furniture, home decor, and household retail.'],
                ['key' => 'hardware_building', 'label' => 'Hardware & Building', 'icon' => 'inventory', 'sort_order' => 90, 'is_enabled' => true, 'description' => 'Hardware, building materials, and tools.'],
                ['key' => 'beauty_cosmetics', 'label' => 'Beauty & Cosmetics', 'icon' => 'marketing', 'sort_order' => 100, 'is_enabled' => true, 'description' => 'Beauty, cosmetics, and personal care.'],
                ['key' => 'books_stationery', 'label' => 'Books & Stationery', 'icon' => 'blog', 'sort_order' => 110, 'is_enabled' => true, 'description' => 'Books, stationery, and gifts.'],
                ['key' => 'general_retail', 'label' => 'General Retail', 'icon' => 'sales', 'sort_order' => 120, 'is_enabled' => true, 'description' => 'General-purpose retail stores.'],
                ['key' => 'other', 'label' => 'Other', 'icon' => 'module', 'sort_order' => 999, 'is_enabled' => true, 'description' => 'Another retail business type.'],
            ],
        ));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['mobile']);
            $table->dropColumn(['mobile', 'verification_status', 'verification_completed_at', 'requires_password_change']);
        });
        Schema::dropIfExists('account_verifications');
        Schema::dropIfExists('saas_industries');
    }
};
