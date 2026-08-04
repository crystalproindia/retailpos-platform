<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('company_logo_path')->nullable()->after('trade_name');
            $table->string('invoice_logo_path')->nullable()->after('company_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['company_logo_path', 'invoice_logo_path']);
        });
    }
};
