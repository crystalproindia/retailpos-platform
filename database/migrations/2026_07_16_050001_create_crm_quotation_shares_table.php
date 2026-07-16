<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quotation_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('crm_quotations')->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('recipient')->nullable();
            $table->string('status', 24);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['quotation_id', 'created_at'], 'crm_qshare_quote_created_idx');
            $table->index(['channel', 'status'], 'crm_qshare_channel_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quotation_shares');
    }
};
