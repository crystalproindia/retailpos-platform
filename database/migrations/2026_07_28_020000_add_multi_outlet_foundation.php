<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            if (! Schema::hasColumn('branches', 'invoice_prefix')) $table->string('invoice_prefix', 16)->nullable();
            if (! Schema::hasColumn('branches', 'receipt_prefix')) $table->string('receipt_prefix', 24)->nullable();
            if (! Schema::hasColumn('branches', 'updated_by')) $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('branches', 'archived_at')) $table->timestamp('archived_at')->nullable();
        });

        Schema::create('branch_user_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'user_id'], 'branch_user_assignment_unique');
            $table->index(['company_id', 'user_id', 'is_active'], 'branch_user_company_user_active_idx');
        });

        foreach (DB::table('companies')->orderBy('id')->cursor() as $company) {
            $primary = DB::table('branches')->where('company_id', $company->id)->orderByDesc('is_primary')->orderBy('id')->first();

            if (! $primary) {
                DB::table('branches')->insert([
                    'company_id' => $company->id,
                    'name' => 'Main Outlet',
                    'legal_name' => $company->legal_name ?? $company->name,
                    'code' => 'MAIN',
                    'email' => $company->email,
                    'phone' => $company->phone,
                    'address' => $company->address,
                    'city' => $company->city,
                    'state' => $company->state,
                    'postal_code' => $company->postal_code,
                    'country' => $company->country ?? 'India',
                    'country_code' => 'IN',
                    'tax_number' => $company->tax_id,
                    'timezone' => $company->timezone,
                    'currency' => $company->currency,
                    'is_primary' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $primary = DB::table('branches')->where('company_id', $company->id)->where('code', 'MAIN')->first();
            }

            DB::table('branches')->where('company_id', $company->id)->where('id', '!=', $primary->id)->update(['is_primary' => false]);
            DB::table('branches')->where('id', $primary->id)->update(['is_primary' => true, 'is_active' => true]);
            DB::table('users')->where('company_id', $company->id)->whereNull('branch_id')->update(['branch_id' => $primary->id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user_assignments');
        Schema::table('branches', function (Blueprint $table): void {
            if (Schema::hasColumn('branches', 'updated_by')) $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(array_filter(['invoice_prefix', 'receipt_prefix', 'archived_at'], fn (string $column): bool => Schema::hasColumn('branches', $column)));
        });
    }
};
