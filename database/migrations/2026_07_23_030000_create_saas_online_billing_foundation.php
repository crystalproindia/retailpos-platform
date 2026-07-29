<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saas_billing_checkout_sessions')) {
            Schema::create('saas_billing_checkout_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->foreignId('saas_subscription_invoice_id');
                $table->foreignId('saas_subscription_id');
                $table->foreignId('integration_connection_id')->nullable();
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
            });
        }

        if (! Schema::hasTable('saas_billing_webhook_events')) {
            Schema::create('saas_billing_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('integration_connection_id')->nullable();
                $table->foreignId('company_id')->nullable();
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
            });
        }

        $this->ensureForeign('saas_billing_checkout_sessions', 'company_id', 'companies', 'saas_checkout_company_fk', 'cascade');
        $this->ensureForeign('saas_billing_checkout_sessions', 'saas_subscription_invoice_id', 'saas_subscription_invoices', 'saas_checkout_invoice_fk', 'restrict');
        $this->ensureForeign('saas_billing_checkout_sessions', 'saas_subscription_id', 'saas_subscriptions', 'saas_checkout_subscription_fk', 'restrict');
        $this->ensureForeign('saas_billing_checkout_sessions', 'integration_connection_id', 'integration_connections', 'saas_checkout_integration_fk', 'null');
        $this->ensureUnique('saas_billing_checkout_sessions', ['company_id', 'idempotency_key'], 'saas_checkout_company_idem_uq');
        $this->ensureUnique('saas_billing_checkout_sessions', ['provider', 'provider_order_id'], 'saas_checkout_provider_order_uq');
        $this->ensureIndex('saas_billing_checkout_sessions', ['saas_subscription_invoice_id', 'status'], 'saas_checkout_invoice_status_idx');
        $this->ensureIndex('saas_billing_checkout_sessions', ['company_id', 'status', 'expires_at'], 'saas_checkout_company_expiry_idx');

        $this->ensureForeign('saas_billing_webhook_events', 'integration_connection_id', 'integration_connections', 'saas_webhook_integration_fk', 'null');
        $this->ensureForeign('saas_billing_webhook_events', 'company_id', 'companies', 'saas_webhook_company_fk', 'null');
        $this->ensureUnique('saas_billing_webhook_events', ['provider', 'provider_event_id'], 'saas_webhook_provider_event_uq');
        $this->ensureIndex('saas_billing_webhook_events', ['provider', 'status', 'received_at'], 'saas_webhook_provider_status_idx');
        $this->ensureIndex('saas_billing_webhook_events', ['company_id', 'event_type'], 'saas_webhook_company_type_idx');
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_billing_webhook_events');
        Schema::dropIfExists('saas_billing_checkout_sessions');
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasIndex($table, $columns)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
        }
    }

    /** @param list<string> $columns */
    private function ensureUnique(string $table, array $columns, string $name): void
    {
        if (! Schema::hasIndex($table, $columns, 'unique')) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique($columns, $name));
        }
    }

    private function ensureForeign(string $table, string $column, string $referenceTable, string $name, string $onDelete): void
    {
        if (Schema::hasForeignKey($table, [$column])) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referenceTable, $name, $onDelete): void {
            $foreign = $blueprint->foreign($column, $name)->references('id')->on($referenceTable);
            match ($onDelete) {
                'cascade' => $foreign->cascadeOnDelete(),
                'null' => $foreign->nullOnDelete(),
                default => $foreign->restrictOnDelete(),
            };
        });
    }
};
