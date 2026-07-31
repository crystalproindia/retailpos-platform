<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workforce_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('primary_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('workforce_employees')->nullOnDelete();
            $table->string('employee_number');
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('work_email')->nullable();
            $table->string('work_mobile', 32)->nullable();
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('status')->default('draft');
            $table->text('manager_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'employee_number'], 'workforce_employee_company_number_uq');
            $table->index(['company_id', 'status'], 'workforce_employee_company_status_idx');
        });

        Schema::create('workforce_employee_outlet_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['employee_id', 'branch_id'], 'workforce_employee_outlet_uq');
            $table->index(['company_id', 'branch_id'], 'workforce_employee_company_outlet_idx');
        });

        Schema::create('workforce_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('base_role')->default('staff');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'name'], 'workforce_role_company_name_uq');
        });
        Schema::create('workforce_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workforce_role_id')->constrained()->cascadeOnDelete();
            $table->string('permission_key');
            $table->timestamps();
            $table->unique(['workforce_role_id', 'permission_key'], 'workforce_role_permission_uq');
        });

        Schema::create('workforce_employee_warehouse_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['employee_id', 'warehouse_id'], 'workforce_employee_warehouse_uq');
        });
        Schema::create('workforce_employee_register_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->cascadeOnDelete();
            $table->foreignId('register_id')->constrained('pos_registers')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['employee_id', 'register_id'], 'workforce_employee_register_uq');
        });

        Schema::create('workforce_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'email', 'expires_at'], 'workforce_invitation_lookup_idx');
        });
        Schema::create('workforce_manager_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_starts_at');
            $table->date('period_ends_at');
            $table->string('cycle');
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('customer_service')->nullable();
            $table->unsignedTinyInteger('product_knowledge')->nullable();
            $table->unsignedTinyInteger('teamwork')->nullable();
            $table->unsignedTinyInteger('reliability')->nullable();
            $table->unsignedTinyInteger('communication')->nullable();
            $table->unsignedTinyInteger('initiative')->nullable();
            $table->text('comments')->nullable();
            $table->text('employee_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'period_starts_at', 'period_ends_at'], 'workforce_review_period_uq');
        });
        Schema::create('workforce_recognitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('workforce_employees')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message')->nullable();
            $table->date('recognized_on');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('workforce_employee_id')->nullable()->after('branch_id')->constrained('workforce_employees')->nullOnDelete();
            $table->foreignId('workforce_role_id')->nullable()->after('role')->constrained('workforce_roles')->nullOnDelete();
            $table->string('account_status')->default('active')->after('is_active');
            $table->timestamp('suspended_at')->nullable()->after('last_login_at');
            $table->index(['company_id', 'workforce_employee_id'], 'users_company_employee_idx');
            $table->unique(['workforce_employee_id'], 'users_workforce_employee_uq');
        });
    }

    public function down(): void
    {
        // Production remediation is forward-only; this is for local development only.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_company_employee_idx');
            $table->dropUnique('users_workforce_employee_uq');
            $table->dropConstrainedForeignId('workforce_role_id');
            $table->dropConstrainedForeignId('workforce_employee_id');
            $table->dropColumn(['account_status', 'suspended_at']);
        });
        Schema::dropIfExists('workforce_recognitions');
        Schema::dropIfExists('workforce_manager_reviews');
        Schema::dropIfExists('workforce_invitations');
        Schema::dropIfExists('workforce_employee_register_assignments');
        Schema::dropIfExists('workforce_employee_warehouse_assignments');
        Schema::dropIfExists('workforce_employee_outlet_assignments');
        Schema::dropIfExists('workforce_role_permissions');
        Schema::dropIfExists('workforce_roles');
        Schema::dropIfExists('workforce_employees');
    }
};
