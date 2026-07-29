<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_delivery_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['notification_delivery_id', 'occurred_at'], 'notif_deliv_evt_delivery_time_idx');
            $table->unique(['company_id', 'provider', 'provider_event_id'], 'notif_deliv_evt_provider_event_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_events');
    }
};
