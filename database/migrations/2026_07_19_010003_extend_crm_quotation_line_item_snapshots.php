<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table): void {
            $table->string('unit', 32)->default('unit')->after('quantity');
            $table->string('discount_type', 16)->default('fixed')->after('discount_amount');
            $table->decimal('discount_percentage', 8, 3)->default(0)->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'discount_type', 'discount_percentage']);
        });
    }
};
