<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_revalidation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 80)->nullable();
            $table->string('slug')->nullable();
            $table->string('path');
            $table->string('status', 40);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at'], 'cms_reval_co_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_revalidation_logs');
    }
};
