<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_forecast_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('forecast_type', 50);
            $table->string('algorithm_version', 50);
            $table->json('parameters')->nullable();
            $table->date('training_start')->nullable();
            $table->date('training_end')->nullable();
            $table->date('forecast_start')->nullable();
            $table->date('forecast_end')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('data_points')->default(0);
            $table->string('confidence_level', 16)->nullable();
            $table->string('safe_error_message', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'forecast_type', 'status'], 'ai_run_company_type_status_idx');
            $table->index(['company_id', 'completed_at'], 'ai_run_company_completed_idx');
        });

        Schema::create('ai_forecast_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forecast_run_id')->constrained('ai_forecast_runs')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('predicted_value', 16, 3)->nullable();
            $table->decimal('lower_bound', 16, 3)->nullable();
            $table->decimal('upper_bound', 16, 3)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('classification', 40)->nullable();
            $table->json('explanation')->nullable();
            $table->json('supporting_metrics')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'classification'], 'ai_result_company_class_idx');
            $table->index(['company_id', 'outlet_id', 'product_id'], 'ai_result_company_outlet_product_idx');
            $table->index(['company_id', 'lead_id'], 'ai_result_company_lead_idx');
        });

        Schema::create('ai_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type', 60);
            $table->string('severity', 16)->default('info');
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('title', 255);
            $table->text('explanation');
            $table->text('recommended_action')->nullable();
            $table->json('evidence')->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'severity'], 'ai_insight_company_status_severity_idx');
            $table->index(['company_id', 'insight_type', 'expires_at'], 'ai_insight_company_type_expiry_idx');
            $table->index(['company_id', 'entity_type', 'entity_id'], 'ai_insight_company_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('ai_forecast_results');
        Schema::dropIfExists('ai_forecast_runs');
    }
};
