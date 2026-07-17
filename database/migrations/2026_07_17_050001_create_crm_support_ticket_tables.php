<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('crm_customers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('onboarding_id')->nullable()->constrained('crm_customer_onboardings')->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained('crm_proforma_invoices')->nullOnDelete();
            $table->string('ticket_number', 32)->unique('crm_spt_number_uq');
            $table->string('subject');
            $table->text('description');
            $table->string('category', 32);
            $table->string('priority', 16)->default('normal');
            $table->string('status', 32)->default('new');
            $table->string('source', 32)->default('internal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reported_by_name')->nullable();
            $table->string('reported_by_email')->nullable();
            $table->string('reported_by_phone')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->text('internal_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'due_at'], 'crm_spt_company_status_due_ix');
            $table->index(['company_id', 'priority', 'status'], 'crm_spt_company_priority_status_ix');
            $table->index(['company_id', 'assigned_to', 'updated_at'], 'crm_spt_company_assigned_updated_ix');
            $table->index(['company_id', 'customer_id', 'created_at'], 'crm_spt_company_customer_created_ix');
            $table->index(['company_id', 'reported_by_email'], 'crm_spt_company_email_ix');
            $table->index(['company_id', 'reported_by_phone'], 'crm_spt_company_phone_ix');
            $table->index(['company_id', 'created_at'], 'crm_spt_company_created_ix');
        });

        Schema::create('crm_support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('crm_support_tickets')->cascadeOnDelete();
            $table->text('message');
            $table->string('visibility', 20)->default('internal');
            $table->string('message_type', 20)->default('note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at'], 'crm_spt_msg_ticket_created_ix');
        });

        Schema::create('crm_support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('crm_support_tickets')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('crm_support_ticket_messages')->nullOnDelete();
            $table->string('title');
            $table->string('file_path', 1000)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ticket_id', 'created_at'], 'crm_spt_attach_ticket_created_ix');
        });

        Schema::create('crm_support_ticket_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('crm_support_tickets')->cascadeOnDelete();
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at'], 'crm_spt_hist_ticket_created_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_support_ticket_status_histories');
        Schema::dropIfExists('crm_support_ticket_attachments');
        Schema::dropIfExists('crm_support_ticket_messages');
        Schema::dropIfExists('crm_support_tickets');
    }
};
