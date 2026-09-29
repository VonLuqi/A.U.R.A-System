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
     * Adds multi-tenant ownership columns. `user_id` stays nullable until
     * backfill (`aura:backfill-transaction-user-id` or the follow-up migration).
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('source_kind', 20)
                ->default('import')
                ->after('statement_import_id');

            $table->index(['user_id', 'occurred_on']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'category_id']);
        });

        // Drop FK so we can loosen nullability (import-backed rows keep the FK after).
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['statement_import_id']);
        });

        DB::statement('ALTER TABLE transactions MODIFY statement_import_id BIGINT UNSIGNED NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('statement_import_id')
                ->references('id')
                ->on('statement_imports')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'occurred_on']);
            $table->dropIndex(['user_id', 'type']);
            $table->dropIndex(['user_id', 'category_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'source_kind']);
        });

        // Restore NOT NULL on statement_import_id (manual rows must not exist yet on rollback).
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['statement_import_id']);
        });

        DB::statement('ALTER TABLE transactions MODIFY statement_import_id BIGINT UNSIGNED NOT NULL');

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('statement_import_id')
                ->references('id')
                ->on('statement_imports')
                ->cascadeOnDelete();
        });
    }
};
