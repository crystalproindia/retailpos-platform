<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_public_signup_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_token_hash', 64)->unique();
            $table->string('idempotency_key', 80)->unique();
            $table->string('industry_key', 80);
            $table->string('verification_method', 16);
            $table->string('email')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('verification_code_hash')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->unsignedTinyInteger('verification_max_attempts')->default(5);
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('resend_available_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->foreignId('saas_tenant_onboarding_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->string('terms_version', 80)->nullable();
            $table->string('privacy_version', 80)->nullable();
            $table->string('signup_source', 40)->default('public');
            $table->string('started_ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
            $table->index(['email', 'verified_at'], 'saas_public_signup_email_idx');
            $table->index(['mobile', 'verified_at'], 'saas_public_signup_mobile_idx');
            $table->index(['expires_at', 'provisioned_at'], 'saas_public_signup_expiry_idx');
        });

        Schema::table('saas_tenant_onboardings', function (Blueprint $table): void {
            $table->string('signup_source', 40)->default('platform_admin')->after('current_stage');
        });
    }

    public function down(): void
    {
        Schema::table('saas_tenant_onboardings', function (Blueprint $table): void {
            $table->dropColumn('signup_source');
        });

        Schema::dropIfExists('saas_public_signup_sessions');
    }
};
