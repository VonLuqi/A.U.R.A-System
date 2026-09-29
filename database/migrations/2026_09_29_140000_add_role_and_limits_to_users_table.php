<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('visitor')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->unsignedInteger('uploads_used')->default(0)->after('is_active');
            $table->unsignedInteger('manual_transactions_used')->default(0)->after('uploads_used');
            $table->timestamp('quota_period_starts_at')->nullable()->after('manual_transactions_used');

            $table->index('role');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'role',
                'is_active',
                'uploads_used',
                'manual_transactions_used',
                'quota_period_starts_at',
            ]);
        });
    }
};
