<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->boolean('database_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->boolean('webhook_enabled')->default(false);
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'user_id', 'event_key'], 'notif_pref_company_user_event_uq');
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_key');
            $table->string('channel');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('locale', 12)->default('en');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'event_key', 'channel', 'locale'], 'notif_tmpl_company_event_channel_locale_idx');
        });

        Schema::create('domain_event_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key');
            $table->string('event_class');
            $table->string('aggregate_type')->nullable();
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->string('correlation_id')->unique();
            $table->string('causation_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('recorded');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'event_key', 'status']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_event_log_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('notification_id')->nullable();
            $table->string('event_key');
            $table->string('channel');
            $table->string('recipient')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'event_key', 'channel', 'status'], 'notif_deliv_company_event_channel_status_idx');
            $table->index(['company_id', 'user_id']);
            $table->index(['domain_event_log_id']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->text('secret');
            $table->json('subscribed_events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_event_log_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key');
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'event_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('domain_event_logs');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
