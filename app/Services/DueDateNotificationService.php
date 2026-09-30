<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\CreditCardDueNotification;
use App\Notifications\LoanDueNotification;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Sends due-date reminders with daily / unread deduplication
 * (PLAN_CARTOES_EMPRESTIMOS §4.2).
 */
final class DueDateNotificationService
{
    public const RESULT_NOTIFIED = 'notified';

    public const RESULT_SKIPPED_DUPE = 'skipped_dupe';

    /**
     * @return self::RESULT_NOTIFIED|self::RESULT_SKIPPED_DUPE
     */
    public function notifyCreditCardDue(
        User $user,
        CreditCard $card,
        ?Carbon $dueOn = null,
        bool $dryRun = false,
    ): string {
        if ($this->alreadyNotified(
            $user,
            CreditCardDueNotification::class,
            'credit_card_id',
            (int) $card->id,
        )) {
            return self::RESULT_SKIPPED_DUPE;
        }

        if (! $dryRun) {
            $user->notify(new CreditCardDueNotification($card, $dueOn));
        }

        return self::RESULT_NOTIFIED;
    }

    /**
     * @return self::RESULT_NOTIFIED|self::RESULT_SKIPPED_DUPE
     */
    public function notifyLoanDue(
        User $user,
        Loan $loan,
        bool $dryRun = false,
    ): string {
        if ($this->alreadyNotified(
            $user,
            LoanDueNotification::class,
            'loan_id',
            (int) $loan->id,
        )) {
            return self::RESULT_SKIPPED_DUPE;
        }

        if (! $dryRun) {
            $user->notify(new LoanDueNotification($loan));
        }

        return self::RESULT_NOTIFIED;
    }

    /**
     * Skip when an unread notification exists for the same entity, or any
     * matching notification was created today (app timezone).
     */
    public function alreadyNotified(
        User $user,
        string $notificationClass,
        string $entityKey,
        int $entityId,
    ): bool {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $todayStart = Carbon::now($tz)->startOfDay();

        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('type', $notificationClass)
            ->where(function ($query) use ($todayStart): void {
                $query->whereNull('read_at')
                    ->orWhere('created_at', '>=', $todayStart);
            })
            ->whereRaw(
                'CAST(JSON_UNQUOTE(JSON_EXTRACT(`data`, ?)) AS UNSIGNED) = ?',
                ['$.'.$entityKey, $entityId],
            )
            ->exists();
    }
}
