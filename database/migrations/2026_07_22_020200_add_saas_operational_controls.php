<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_subscription_events', function (Blueprint $table): void {
            $table->string('idempotency_key', 160)->nullable()->after('event_key');
            $table->unique(['saas_subscription_id', 'idempotency_key'], 'saas_sub_event_idempotency_uq');
        });

        Schema::create('saas_usage_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('usage_key', 100);
            $table->unsignedBigInteger('current_value');
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->string('state', 32);
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['company_id', 'usage_key'], 'saas_usage_company_key_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_usage_snapshots');
        Schema::table('saas_subscription_events', function (Blueprint $table): void {
            $table->dropUnique('saas_sub_event_idempotency_uq');
            $table->dropColumn('idempotency_key');
        });
    }
};
