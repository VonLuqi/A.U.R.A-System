<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convention: `0` means unlimited for numeric limit columns.
     */
    public function up(): void
    {
        Schema::create('role_limits', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20)->unique();
            $table->unsignedInteger('max_uploads')->default(0);
            $table->unsignedInteger('max_manual_transactions')->default(0);
            $table->unsignedInteger('max_date_range_days')->default(0);
            $table->unsignedInteger('max_goals')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_limits');
    }
};
