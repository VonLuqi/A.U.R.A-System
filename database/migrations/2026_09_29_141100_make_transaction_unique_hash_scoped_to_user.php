<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfills `user_id` from statement_imports, enforces NOT NULL, and scopes
     * `unique_hash` uniqueness per user: unique (user_id, unique_hash).
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE transactions t
             INNER JOIN statement_imports si ON t.statement_import_id = si.id
             SET t.user_id = si.user_id
             WHERE t.user_id IS NULL'
        );

        $orphans = (int) DB::table('transactions')->whereNull('user_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException(
                "Cannot scope unique_hash: {$orphans} transaction(s) still have null user_id. ".
                'Run `php artisan aura:backfill-transaction-user-id` or delete orphans.'
            );
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE transactions MODIFY user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->dropUnique(['unique_hash']);
            $table->unique(['user_id', 'unique_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'unique_hash']);
            $table->unique('unique_hash');
            $table->dropForeign(['user_id']);
        });

        DB::statement('ALTER TABLE transactions MODIFY user_id BIGINT UNSIGNED NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
