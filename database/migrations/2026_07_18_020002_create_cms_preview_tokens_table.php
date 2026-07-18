<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_preview_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('previewable_type', 120);
            $table->unsignedBigInteger('previewable_id');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'previewable_type', 'previewable_id'], 'cms_preview_entity_idx');
            $table->index(['expires_at', 'revoked_at'], 'cms_preview_validity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_preview_tokens');
    }
};
