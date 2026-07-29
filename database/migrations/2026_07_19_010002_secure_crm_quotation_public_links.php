<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table): void {
            $table->foreignId('opportunity_id')->nullable()->after('lead_id')->constrained('crm_opportunities')->nullOnDelete();
            $table->string('public_token_hash', 64)->nullable()->unique('crm_quote_public_token_hash_uq');
            $table->timestamp('public_token_expires_at')->nullable();
            $table->timestamp('public_token_revoked_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('public_view_count')->default(0);
            $table->timestamp('public_responded_at')->nullable();
            $table->string('public_response_name')->nullable();
            $table->text('public_response_message')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('parent_quotation_id')->nullable()->constrained('crm_quotations')->nullOnDelete();
            $table->index(['company_id', 'opportunity_id'], 'crm_quote_company_opp_idx');
            $table->index(['company_id', 'status', 'valid_until'], 'crm_quote_status_valid_idx');
        });

        // Existing links are deliberately invalidated rather than retaining plaintext bearer tokens at rest.
        if (DB::getDriverName() === 'mysql') {
            DB::table('crm_quotations')->whereNotNull('public_token')->update([
                'public_token_hash' => DB::raw('SHA2(public_token, 256)'),
                'public_token' => null,
                'public_url' => null,
            ]);
        } else {
            DB::table('crm_quotations')->whereNotNull('public_token')->orderBy('id')->each(function (object $quotation): void {
                DB::table('crm_quotations')->where('id', $quotation->id)->update([
                    'public_token_hash' => hash('sha256', $quotation->public_token),
                    'public_token' => null,
                    'public_url' => null,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table): void {
            $table->dropIndex('crm_quote_company_opp_idx');
            $table->dropIndex('crm_quote_status_valid_idx');
            $table->dropUnique('crm_quote_public_token_hash_uq');
            $table->dropConstrainedForeignId('parent_quotation_id');
            $table->dropColumn([
                'opportunity_id', 'public_token_hash', 'public_token_expires_at', 'public_token_revoked_at',
                'first_viewed_at', 'last_viewed_at', 'public_view_count', 'public_responded_at',
                'public_response_name', 'public_response_message', 'rejection_reason', 'version_number',
            ]);
        });
    }
};
