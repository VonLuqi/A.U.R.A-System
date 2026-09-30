<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa H §1.5 — cotas de cartões e empréstimos em role_limits (`0` = ilimitado).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('role_limits', function (Blueprint $table) {
            $table->unsignedInteger('max_credit_cards')->default(0)->after('max_goals');
            $table->unsignedInteger('max_loans')->default(0)->after('max_credit_cards');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_limits', function (Blueprint $table) {
            $table->dropColumn(['max_credit_cards', 'max_loans']);
        });
    }
};
