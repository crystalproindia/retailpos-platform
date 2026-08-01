<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicable_outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);
            $table->unsignedSmallInteger('unpaid_break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_before_minutes')->default(0);
            $table->unsignedSmallInteger('grace_after_minutes')->default(0);
            $table->unsignedSmallInteger('minimum_work_minutes')->default(0);
            $table->unsignedSmallInteger('standard_work_minutes')->default(480);
            $table->unsignedSmallInteger('overtime_after_minutes')->default(480);
            $table->string('color_token', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code'], 'shift_template_company_code_uq');
            $table->index(['company_id', 'applicable_outlet_id', 'is_active'], 'shift_template_scope_active_idx');
        });

        Schema::create('weekly_offs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('workforce_employees')->nullOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'outlet_id', 'weekday', 'is_active'], 'weekly_off_scope_day_active_idx');
            $table->index(['company_id', 'employee_id', 'weekday', 'is_active'], 'weekly_off_employee_day_idx');
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->date('holiday_date');
            $table->string('holiday_type')->default('paid');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'outlet_id', 'holiday_date'], 'holiday_company_outlet_date_uq');
            $table->index(['company_id', 'holiday_date', 'is_active'], 'holiday_company_date_active_idx');
        });

        Schema::create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_template_id')->constrained('shift_templates')->restrictOnDelete();
            $table->date('work_date');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_source')->default('manual');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['employee_id', 'work_date'], 'shift_assignment_employee_date_uq');
            $table->index(['company_id', 'outlet_id', 'work_date', 'status'], 'shift_assignment_outlet_date_status_idx');
        });

        Schema::create('roster_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('branches')->restrictOnDelete();
            $table->date('week_starts_on');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'outlet_id', 'week_starts_on'], 'roster_publication_company_outlet_week_uq');
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->date('attendance_date');
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedInteger('total_break_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->string('attendance_status')->default('scheduled');
            $table->string('attendance_source')->default('system_generated');
            $table->string('check_in_method')->nullable();
            $table->string('check_out_method')->nullable();
            $table->string('attendance_state')->default('not_started');
            $table->boolean('is_manual')->default(false);
            $table->foreignId('manually_entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('correction_status')->default('none');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'employee_id', 'attendance_date'], 'attendance_employee_day_uq');
            $table->index(['company_id', 'outlet_id', 'attendance_date', 'attendance_status'], 'attendance_outlet_day_status_idx');
            $table->index(['company_id', 'employee_id', 'checked_out_at'], 'attendance_employee_checkout_idx');
        });

        Schema::create('attendance_breaks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->string('break_type')->default('short_break');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['attendance_id', 'ended_at'], 'attendance_break_active_idx');
            $table->index(['company_id', 'employee_id', 'started_at'], 'attendance_break_employee_start_idx');
        });

        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained('attendance_records')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('original_values');
            $table->json('requested_values');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'status'], 'attendance_correction_employee_status_idx');
            $table->index(['company_id', 'status', 'created_at'], 'attendance_correction_queue_idx');
        });

        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_entitlement', 7, 2)->default(0);
            $table->string('accrual_method')->default('manual');
            $table->boolean('carry_forward_allowed')->default(false);
            $table->decimal('maximum_carry_forward', 7, 2)->default(0);
            $table->boolean('negative_balance_allowed')->default(false);
            $table->unsignedSmallInteger('attachment_required_after_days')->nullable();
            $table->boolean('approval_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code'], 'leave_type_company_code_uq');
            $table->index(['company_id', 'is_active'], 'leave_type_company_active_idx');
        });

        Schema::create('employee_leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->string('period', 20);
            $table->decimal('opening_balance', 7, 2)->default(0);
            $table->decimal('accrued', 7, 2)->default(0);
            $table->decimal('used', 7, 2)->default(0);
            $table->decimal('pending', 7, 2)->default(0);
            $table->decimal('adjusted', 7, 2)->default(0);
            $table->decimal('remaining', 7, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'period'], 'leave_balance_employee_type_period_uq');
            $table->index(['company_id', 'employee_id', 'period'], 'leave_balance_employee_period_idx');
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('day_portion')->default('full_day');
            $table->decimal('requested_days', 7, 2);
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'starts_on', 'ends_on'], 'leave_request_employee_dates_idx');
            $table->index(['company_id', 'outlet_id', 'status', 'starts_on'], 'leave_request_outlet_queue_idx');
        });

        Schema::create('leave_balance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_leave_balance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            $table->string('entry_type');
            $table->decimal('amount', 7, 2);
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_leave_balance_id', 'entry_type'], 'leave_balance_tx_balance_type_idx');
        });

        Schema::create('overtime_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained('attendance_records')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->restrictOnDelete();
            $table->unsignedInteger('candidate_minutes')->default(0);
            $table->unsignedInteger('approved_minutes')->default(0);
            $table->string('status')->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique('attendance_id', 'overtime_review_attendance_uq');
            $table->index(['company_id', 'status', 'created_at'], 'overtime_review_company_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_reviews');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balance_transactions');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('roster_publications');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('weekly_offs');
        Schema::dropIfExists('shift_templates');
    }
};
