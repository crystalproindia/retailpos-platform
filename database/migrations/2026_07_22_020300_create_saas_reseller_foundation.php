<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_resellers', function (Blueprint $table): void {
            $table->id();
            $table->string('partner_code', 80)->unique();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 80)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->string('referral_source', 100)->nullable();
            $table->date('agreement_starts_at')->nullable();
            $table->date('agreement_ends_at')->nullable();
            $table->json('discount_metadata')->nullable();
            $table->json('commission_metadata')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('saas_reseller_tenant_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saas_reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['saas_reseller_id', 'company_id', 'unassigned_at'], 'saas_reseller_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_reseller_tenant_assignments');
        Schema::dropIfExists('saas_resellers');
    }
};
