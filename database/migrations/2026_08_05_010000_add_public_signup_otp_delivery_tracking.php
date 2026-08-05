<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->text('sensitive_payload')->nullable()->after('payload');
        });

        Schema::table('saas_public_signup_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('verification_sequence')->default(1)->after('verification_code_hash');
            $table->foreignId('verification_delivery_id')->nullable()->after('verification_sequence')->constrained('notification_deliveries')->nullOnDelete();
            $table->string('verification_delivery_status', 32)->nullable()->after('verification_delivery_id');
        });
    }

    public function down(): void
    {
        Schema::table('saas_public_signup_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verification_delivery_id');
            $table->dropColumn(['verification_sequence', 'verification_delivery_status']);
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropColumn('sensitive_payload');
        });
    }
};
