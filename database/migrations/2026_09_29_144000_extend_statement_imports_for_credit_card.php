<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand format/source string columns for credit-card imports.
     *
     * Kept as VARCHAR (not MySQL ENUM) so new formats can ship without ALTER ENUM.
     * `csv_credit_card` needs > 10 chars (previous format column width).
     */
    public function up(): void
    {
        if (! Schema::hasTable('statement_imports')) {
            return;
        }

        DB::statement('ALTER TABLE statement_imports MODIFY format VARCHAR(32) NOT NULL');
        DB::statement('ALTER TABLE statement_imports MODIFY source VARCHAR(32) NOT NULL DEFAULT \'nubank\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('statement_imports')) {
            return;
        }

        // Truncate-safe only if no csv_credit_card / long source rows exist.
        DB::statement('ALTER TABLE statement_imports MODIFY format VARCHAR(10) NOT NULL');
        DB::statement('ALTER TABLE statement_imports MODIFY source VARCHAR(30) NOT NULL DEFAULT \'nubank\'');
    }
};
