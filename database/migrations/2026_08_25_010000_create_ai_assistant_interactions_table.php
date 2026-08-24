<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->uuid('conversation_id');
            $table->string('intent', 40);
            $table->string('provider', 40)->default('deterministic');
            $table->string('model', 80)->nullable();
            $table->string('status', 20);
            $table->char('prompt_digest', 64);
            $table->unsignedInteger('context_fact_count')->default(0);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_error_code', 60)->nullable();
            $table->json('date_scope')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'created_at'], 'ai_interaction_company_user_created_idx');
            $table->index(['company_id', 'intent', 'status'], 'ai_interaction_company_intent_status_idx');
            $table->index(['conversation_id', 'created_at'], 'ai_interaction_conversation_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_interactions');
    }
};
