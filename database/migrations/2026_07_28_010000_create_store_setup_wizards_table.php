<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_setup_wizards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('draft');
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->string('industry_key', 80);
            $table->json('answers')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('recommendation_version', 24)->nullable();
            $table->string('applied_version', 24)->nullable();
            $table->string('idempotency_key', 80)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_resumed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at'], 'store_setup_status_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_setup_wizards');
    }
};
