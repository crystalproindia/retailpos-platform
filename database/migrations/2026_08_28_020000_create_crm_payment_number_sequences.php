<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_payment_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope_key', 40);
            $table->string('sequence_type', 32);
            $table->unsignedSmallInteger('calendar_year');
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['scope_key', 'sequence_type', 'calendar_year'], 'crm_pay_num_sequence_scope_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_payment_number_sequences');
    }
};
