<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 80);
            $table->string('name');
            $table->string('account_email')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 40)->default('disconnected');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider'], 'int_conn_company_provider_uq');
            $table->index('provider', 'int_conn_provider_idx');
            $table->index('status', 'int_conn_status_idx');
            $table->index('account_email', 'int_conn_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
