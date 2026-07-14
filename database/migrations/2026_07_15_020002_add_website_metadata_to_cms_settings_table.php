<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_settings', function (Blueprint $table): void {
            $table->string('group')->default('general');
            $table->boolean('is_public')->default(true);
            $table->index(['company_id', 'group', 'key'], 'cms_set_company_group_key_ix');
        });
    }

    public function down(): void
    {
        Schema::table('cms_settings', function (Blueprint $table): void {
            $table->dropIndex('cms_set_company_group_key_ix');
            $table->dropColumn(['group', 'is_public']);
        });
    }
};
