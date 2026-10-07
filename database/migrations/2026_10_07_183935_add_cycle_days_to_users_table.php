<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('expense_cycle_day')->nullable()->after('avatar_path');
            $table->unsignedTinyInteger('income_cycle_day')->nullable()->after('expense_cycle_day');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['expense_cycle_day', 'income_cycle_day']);
        });
    }
};
