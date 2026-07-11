<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('category');
            $table->string('status');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['company_id', 'category', 'status']);
            $table->index(['key', 'checked_at']);
        });

        Schema::create('scheduled_task_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('command');
            $table->string('description')->nullable();
            $table->string('status');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('output')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['command', 'status']);
            $table->index('started_at');
        });

        Schema::create('queue_job_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('processed_count')->nullable();
            $table->unsignedInteger('reserved_count')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['queue', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_job_snapshots');
        Schema::dropIfExists('scheduled_task_runs');
        Schema::dropIfExists('system_health_checks');
    }
};
