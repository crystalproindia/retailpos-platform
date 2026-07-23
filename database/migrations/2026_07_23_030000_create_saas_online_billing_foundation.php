<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_billing_checkout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saas_subscription_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('saas_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('integration_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 80);
            $table->string('status', 24)->default('created');
            $table->string('provider_order_id', 160)->nullable();
            $table->string('provider_payment_id', 160)->nullable();
            $table->string('currency', 3);
            $table->decimal('amount', 14, 2);
            $table->string('idempotency_key', 160);
            $table->json('metadata')->nullable();
            $table->string('return_path', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key'], 'saas_checkout_company_idem_uq');
            $table->unique(['provider', 'provider_order_id'], 'saas_checkout_provider_order_uq');
            $table->index(['saas_subscription_invoice_id', 'status'], 'saas_checkout_invoice_status_idx');
            $table->index(['company_id', 'status', 'expires_at'], 'saas_checkout_company_expiry_idx');
        });

        Schema::create('saas_billing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 80);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 120);
            $table->string('status', 24)->default('received');
            $table->string('signature', 512)->nullable();
            $table->string('payload_hash', 64);
            $table->longText('raw_payload');
            $table->json('normalized_payload')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('safe_failure_reason', 1000)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'saas_webhook_provider_event_uq');
            $table->index(['provider', 'status', 'received_at'], 'saas_webhook_provider_status_idx');
            $table->index(['company_id', 'event_type'], 'saas_webhook_company_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_webhook_events');
        Schema::dropIfExists('saas_billing_checkout_sessions');
    }
};
