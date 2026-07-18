<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_email_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('encryption', 16)->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('reply_to_address')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id', 'company_email_settings_company_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_email_settings');
    }
};
