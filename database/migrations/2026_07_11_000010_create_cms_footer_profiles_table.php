<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_footer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp')->nullable();
            $table->text('business_hours')->nullable();
            $table->string('google_map_url')->nullable();
            $table->string('copyright_text')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_footer_profiles');
    }
};
