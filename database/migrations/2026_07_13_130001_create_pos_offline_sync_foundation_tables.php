<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->uuid('offline_uuid')->nullable()->after('sale_number');
            $table->string('offline_reference')->nullable()->after('offline_uuid');
            $table->boolean('synced_from_offline')->default(false)->after('offline_reference');
            $table->timestamp('offline_created_at')->nullable()->after('synced_from_offline');
            $table->string('device_id')->nullable()->after('offline_created_at');
            $table->unique(['company_id', 'offline_uuid']);
        });

        Schema::create('pos_offline_sync_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('batch_uuid');
            $table->string('device_id')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('synced_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'batch_uuid']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('pos_offline_sync_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('pos_offline_sync_batches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('offline_uuid');
            $table->string('device_id')->nullable();
            $table->string('record_type')->default('bill');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->string('server_reference_type')->nullable();
            $table->unsignedBigInteger('server_reference_id')->nullable();
            $table->text('error_message')->nullable();
            $table->text('warning_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'offline_uuid']);
            $table->index(['company_id', 'status', 'record_type'], 'pos_off_sync_record_company_status_type_idx');
        });

        Schema::create('pos_offline_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('enable_offline_pos')->default(true);
            $table->boolean('enable_auto_sync')->default(true);
            $table->boolean('allow_offline_cash')->default(true);
            $table->boolean('allow_offline_manual_card')->default(true);
            $table->boolean('allow_offline_manual_upi')->default(true);
            $table->boolean('allow_offline_wallet_usage')->default(false);
            $table->boolean('allow_offline_loyalty_redemption')->default(false);
            $table->string('offline_stock_conflict_strategy')->default('sync_with_warning');
            $table->unsignedInteger('offline_data_cache_minutes')->default(60);
            $table->decimal('max_offline_bill_amount', 14, 2)->nullable();
            $table->unsignedInteger('max_offline_bills_before_sync')->nullable();
            $table->timestamps();
            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_offline_settings');
        Schema::dropIfExists('pos_offline_sync_records');
        Schema::dropIfExists('pos_offline_sync_batches');
        Schema::table('pos_sales', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'offline_uuid']);
            $table->dropColumn(['offline_uuid', 'offline_reference', 'synced_from_offline', 'offline_created_at', 'device_id']);
        });
    }
};
