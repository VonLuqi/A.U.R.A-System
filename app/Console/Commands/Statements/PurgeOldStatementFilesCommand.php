<?php

namespace App\Console\Commands\Statements;

use App\Services\StatementRetentionService;
use Illuminate\Console\Command;

class PurgeOldStatementFilesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'statements:purge-files
                            {--days= : Retention days (default: STATEMENT_RETENTION_DAYS or 90)}
                            {--dry-run : List candidates without deleting files}';

    /**
     * @var string
     */
    protected $description = 'Purge statement files older than the retention window (default 90 days)';

    public function handle(StatementRetentionService $retention): int
    {
        $days = (int) ($this->option('days')
            ?: env('STATEMENT_RETENTION_DAYS', StatementRetentionService::DEFAULT_RETENTION_DAYS));
        $dryRun = (bool) $this->option('dry-run');

        $result = $retention->purgeOlderThan($days, $dryRun);

        if ($dryRun) {
            $this->info("Dry-run: {$result['candidates']} candidate(s) older than {$result['days']} day(s).");
        } else {
            $this->info(
                "Purged {$result['purged']} file(s); ".
                "{$result['missing']} already missing; ".
                "{$result['candidates']} import(s) marked purged_at ".
                "(retention {$result['days']} day(s))."
            );
        }

        return self::SUCCESS;
    }
}
