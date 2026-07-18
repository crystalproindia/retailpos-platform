<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->string('template_key')->nullable()->after('event_key');
            $table->string('subject')->nullable()->after('recipient');
            $table->string('recipient_name')->nullable()->after('recipient');
            $table->string('related_type')->nullable()->after('notification_id');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            $table->string('idempotency_key', 96)->nullable()->after('provider_message_id');
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();

            $table->index(['company_id', 'related_type', 'related_id'], 'notif_deliv_company_related_idx');
            $table->unique(['company_id', 'idempotency_key'], 'notif_deliv_company_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropUnique('notif_deliv_company_idempotency_uq');
            $table->dropIndex('notif_deliv_company_related_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['template_key', 'subject', 'recipient_name', 'related_type', 'related_id', 'idempotency_key']);
        });
    }
};
