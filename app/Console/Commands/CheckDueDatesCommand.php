<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Services\DueDateNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily due-date reminders for credit cards and loans
 * (PLAN_CARTOES_EMPRESTIMOS §4.3).
 */
class CheckDueDatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aura:check-due-dates
                            {--dry-run : Evaluate windows without writing notifications}';

    /**
     * @var string
     */
    protected $description = 'Notify users of upcoming credit-card and loan due dates';

    public function handle(DueDateNotificationService $notifier): int
    {
        if (! (bool) config('aura.features.notifications', false)) {
            $message = 'aura:check-due-dates skipped — feature notifications is off.';
            Log::info($message);
            $this->info($message);

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $today = Carbon::now($tz)->startOfDay();

        $cardDays = max(0, (int) config('aura.notifications.credit_card_due_days', 3));
        $loanDays = max(0, (int) config('aura.notifications.loan_due_days', 3));
        $cardWindowEnd = $today->copy()->addDays($cardDays);
        $loanWindowEnd = $today->copy()->addDays($loanDays);

        $cardsNotified = 0;
        $loansNotified = 0;
        $skippedDupes = 0;

        User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (
                $notifier,
                $dryRun,
                $today,
                $cardWindowEnd,
                $loanWindowEnd,
                &$cardsNotified,
                &$loansNotified,
                &$skippedDupes,
            ): void {
                foreach ($users as $user) {
                    $this->processUserCards(
                        $user,
                        $notifier,
                        $today,
                        $cardWindowEnd,
                        $dryRun,
                        $cardsNotified,
                        $skippedDupes,
                    );
                    $this->processUserLoans(
                        $user,
                        $notifier,
                        $loanWindowEnd,
                        $dryRun,
                        $loansNotified,
                        $skippedDupes,
                    );
                }
            });

        $prefix = $dryRun ? 'Dry-run: ' : '';
        $summary = sprintf(
            '%scards_notified=%d loans_notified=%d skipped_dupes=%d (card_window=%s..%s loan_window=..%s)',
            $prefix,
            $cardsNotified,
            $loansNotified,
            $skippedDupes,
            $today->toDateString(),
            $cardWindowEnd->toDateString(),
            $loanWindowEnd->toDateString(),
        );

        $this->info($summary);
        Log::info('aura:check-due-dates finished', [
            'dry_run' => $dryRun,
            'cards_notified' => $cardsNotified,
            'loans_notified' => $loansNotified,
            'skipped_dupes' => $skippedDupes,
        ]);

        return self::SUCCESS;
    }

    private function processUserCards(
        User $user,
        DueDateNotificationService $notifier,
        Carbon $today,
        Carbon $cardWindowEnd,
        bool $dryRun,
        int &$cardsNotified,
        int &$skippedDupes,
    ): void {
        $cards = CreditCard::query()
            ->forUser($user)
            ->active()
            ->orderBy('id')
            ->get();

        foreach ($cards as $card) {
            $due = $card->nextDueDate($today);
            if ($due->lt($today) || $due->gt($cardWindowEnd)) {
                continue;
            }

            $result = $notifier->notifyCreditCardDue($user, $card, $due, $dryRun);
            if ($result === DueDateNotificationService::RESULT_SKIPPED_DUPE) {
                $skippedDupes++;
            } else {
                $cardsNotified++;
            }
        }
    }

    private function processUserLoans(
        User $user,
        DueDateNotificationService $notifier,
        Carbon $loanWindowEnd,
        bool $dryRun,
        int &$loansNotified,
        int &$skippedDupes,
    ): void {
        $loans = Loan::query()
            ->forUser($user)
            ->whereIn('status', [LoanStatus::Open, LoanStatus::Partial])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', $loanWindowEnd->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            $result = $notifier->notifyLoanDue($user, $loan, $dryRun);
            if ($result === DueDateNotificationService::RESULT_SKIPPED_DUPE) {
                $skippedDupes++;
            } else {
                $loansNotified++;
            }
        }
    }
}
