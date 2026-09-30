<?php

namespace App\Notifications;

use App\Models\CreditCard;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder that a credit-card invoice is due soon (PLAN_CARTOES_EMPRESTIMOS §4.1).
 *
 * Sync delivery (§4.5): does **not** implement ShouldQueue — HostGator uses
 * QUEUE_CONNECTION=sync and sends inline from `aura:check-due-dates`.
 * If a real queue + worker is introduced later, this class may adopt ShouldQueue;
 * until then sync remains the documented fallback.
 */
class CreditCardDueNotification extends Notification
{
    use Queueable;

    public readonly Carbon $dueOn;

    public function __construct(
        public readonly CreditCard $creditCard,
        ?Carbon $dueOn = null,
    ) {
        $this->dueOn = ($dueOn ?? $creditCard->nextDueDate())->copy()->startOfDay();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->mailChannelEnabled()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $due = $this->dueOn->toDateString();
        $name = (string) $this->creditCard->name;
        $subject = "Fatura {$name} vence em {$due}";

        return (new MailMessage)
            ->subject($subject)
            ->line($this->message())
            ->line("Cartão: {$name}")
            ->line("Vencimento: {$due}");
    }

    /**
     * @return array{type: string, credit_card_id: int, name: string, due_on: string, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'credit_card_due',
            'credit_card_id' => (int) $this->creditCard->id,
            'name' => (string) $this->creditCard->name,
            'due_on' => $this->dueOn->toDateString(),
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        $name = (string) $this->creditCard->name;
        $due = $this->dueOn->toDateString();

        return "Fatura {$name} vence em {$due}.";
    }

    /**
     * Mail only when a mailer is configured and the notifications feature is on.
     */
    private function mailChannelEnabled(): bool
    {
        if (! (bool) config('aura.features.notifications', false)) {
            return false;
        }

        return filled(config('mail.default'));
    }
}
