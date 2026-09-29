<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillTransactionUserIdCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aura:backfill-transaction-user-id
                            {--dry-run : Count rows that would be updated without writing}';

    /**
     * @var string
     */
    protected $description = 'Backfill transactions.user_id from statement_imports and enforce NOT NULL';

    public function handle(): int
    {
        if (! Schema::hasColumn('transactions', 'user_id')) {
            $this->error('Column transactions.user_id does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $pending = (int) DB::table('transactions')->whereNull('user_id')->count();

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$pending} transaction(s) would receive user_id from statement_imports.");

            return self::SUCCESS;
        }

        $updated = DB::update(
            'UPDATE transactions t
             INNER JOIN statement_imports si ON t.statement_import_id = si.id
             SET t.user_id = si.user_id
             WHERE t.user_id IS NULL'
        );

        $remaining = (int) DB::table('transactions')->whereNull('user_id')->count();

        if ($remaining > 0) {
            $this->error(
                "Backfill incomplete: {$remaining} transaction(s) still have null user_id ".
                '(likely missing statement_import_id). Resolve orphans before enforcing NOT NULL.'
            );

            return self::FAILURE;
        }

        // Enforce NOT NULL when still nullable (e.g. mid-deploy before scoped-hash migration).
        $column = collect(DB::select('SHOW COLUMNS FROM transactions LIKE \'user_id\''))->first();
        $isNullable = $column !== null && strtoupper((string) $column->Null) === 'YES';

        if ($isNullable) {
            Schema::table('transactions', function ($table) {
                $table->dropForeign(['user_id']);
            });

            DB::statement('ALTER TABLE transactions MODIFY user_id BIGINT UNSIGNED NOT NULL');

            Schema::table('transactions', function ($table) {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });

            $this->info('Column transactions.user_id is now NOT NULL.');
        }

        $this->info("Backfilled user_id on {$updated} transaction(s). Pending before run: {$pending}.");

        return self::SUCCESS;
    }
}
