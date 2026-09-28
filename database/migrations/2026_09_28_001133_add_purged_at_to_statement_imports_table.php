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
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->timestamp('purged_at')->nullable()->after('error_message');
            $table->index('purged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->dropIndex(['purged_at']);
            $table->dropColumn('purged_at');
        });
    }
};
