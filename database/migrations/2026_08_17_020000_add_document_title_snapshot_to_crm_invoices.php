<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_invoices') && ! Schema::hasColumn('crm_invoices', 'document_title_snapshot')) {
            Schema::table('crm_invoices', function (Blueprint $table): void {
                $table->string('document_title_snapshot', 60)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_invoices') && Schema::hasColumn('crm_invoices', 'document_title_snapshot')) {
            Schema::table('crm_invoices', function (Blueprint $table): void {
                $table->dropColumn('document_title_snapshot');
            });
        }
    }
};
