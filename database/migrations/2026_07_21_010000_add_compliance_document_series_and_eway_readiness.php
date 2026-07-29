<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_document_series', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 32); $table->string('financial_year', 9); $table->string('prefix', 40); $table->unsignedBigInteger('last_sequence')->default(0); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['company_id', 'branch_id', 'document_type', 'financial_year'], 'gst_series_company_branch_type_fy_uq');
        });
        Schema::create('gst_eway_readiness', function (Blueprint $table): void {
            $table->id(); $table->foreignId('company_id')->constrained()->cascadeOnDelete(); $table->string('document_type', 32); $table->string('document_model', 100); $table->unsignedBigInteger('document_id');
            $table->string('status', 24)->default('not_applicable'); $table->date('document_date')->nullable(); $table->string('transport_mode', 24)->nullable(); $table->unsignedInteger('transport_distance')->nullable(); $table->string('transporter_id', 40)->nullable(); $table->string('transporter_name')->nullable(); $table->string('vehicle_number', 32)->nullable(); $table->string('vehicle_type', 16)->nullable();
            $table->text('dispatch_from')->nullable(); $table->text('ship_to')->nullable(); $table->string('reason_for_transport', 255)->nullable(); $table->string('provider_reference', 120)->nullable(); $table->string('safe_error_message', 1000)->nullable(); $table->timestamp('validated_at')->nullable(); $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps();
            $table->unique(['company_id', 'document_model', 'document_id'], 'gst_eway_company_doc_uq');
        });
    }
    public function down(): void { Schema::dropIfExists('gst_eway_readiness'); Schema::dropIfExists('gst_document_series'); }
};
