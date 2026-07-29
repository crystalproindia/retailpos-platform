<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_template_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 48)->default('structured_gst_grid');
            $table->string('brand_color', 16)->default('#0f766e');
            $table->string('copy_label', 24)->default('original');
            $table->string('orientation', 16)->default('portrait');
            $table->string('payment_qr_uri', 512)->nullable();
            $table->json('options')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('company_id', 'invoice_template_settings_company_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_template_settings');
    }
};
