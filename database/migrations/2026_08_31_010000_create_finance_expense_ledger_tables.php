<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('classification', 32);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'expense_category_company_name_uq');
            $table->index(['company_id', 'classification', 'is_active'], 'expense_category_company_class_idx');
        });

        Schema::create('expense_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->string('classification_snapshot', 32);
            $table->date('transaction_date');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->string('payee', 160)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('reference', 160)->nullable();
            $table->string('description', 1000);
            $table->text('notes')->nullable();
            $table->string('receipt_path', 512)->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reverses_expense_transaction_id')->nullable()->constrained('expense_transactions')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reversal_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'branch_id', 'transaction_date', 'status'], 'expense_txn_company_branch_date_idx');
            $table->index(['company_id', 'classification_snapshot', 'status', 'transaction_date'], 'expense_txn_company_class_date_idx');
            $table->unique('reverses_expense_transaction_id', 'expense_txn_reverses_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_transactions');
        Schema::dropIfExists('expense_categories');
    }
};
