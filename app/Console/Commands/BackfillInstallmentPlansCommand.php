<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InstallmentPlanService;
use Illuminate\Console\Command;

class BackfillInstallmentPlansCommand extends Command
{
    protected $signature = 'installments:backfill {--user= : User id (all users if omitted)} {--limit=500}';

    protected $description = 'Backfill installment plans from debit transactions with X/Y titles';

    public function handle(InstallmentPlanService $plans): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $userId = $this->option('user');

        $query = User::query()->orderBy('id');
        if ($userId !== null && $userId !== '') {
            $query->whereKey((int) $userId);
        }

        $total = 0;
        foreach ($query->cursor() as $user) {
            $n = $plans->backfillForUser($user, $limit);
            if ($n > 0) {
                $this->info("User {$user->id}: {$n} transactions");
            }
            $total += $n;
        }

        $this->info("Done. Processed {$total} installment transaction(s).");

        return self::SUCCESS;
    }
}
