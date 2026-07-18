<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('revisionable_type', 120);
            $table->unsignedBigInteger('revisionable_id');
            $table->unsignedInteger('revision_number');
            $table->string('action', 30);
            $table->json('snapshot');
            $table->json('changed_fields')->nullable();
            $table->string('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['revisionable_type', 'revisionable_id', 'revision_number'], 'cms_rev_entity_number_unique');
            $table->index(['company_id', 'created_at'], 'cms_rev_company_created_idx');
            $table->index(['revisionable_type', 'revisionable_id'], 'cms_rev_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_revisions');
    }
};
