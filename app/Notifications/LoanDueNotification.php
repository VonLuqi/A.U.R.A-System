<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder to collect / settle a loan before due_on (PLAN_CARTOES_EMPRESTIMOS §4.1).
 *
 * Sync delivery (§4.5): does **not** implement ShouldQueue — HostGator uses
 * QUEUE_CONNECTION=sync and sends inline from `aura:check-due-dates`.
 * If a real queue + worker is introduced later, this class may adopt ShouldQueue;
 * until then sync remains the documented fallback.
 */
class LoanDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Loan $loan,
    ) {}

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
        $due = $this->dueOnString();
        $debtor = (string) $this->loan->debtor_name;
        $subject = "Cobrar {$debtor} até {$due}";
        $amount = number_format((float) $this->loan->amount, 2, '.', '');

        return (new MailMessage)
            ->subject($subject)
            ->line($this->message())
            ->line("Devedor: {$debtor}")
            ->line("Valor: {$amount} {$this->loan->currency}")
            ->line("Vencimento: {$due}");
    }

    /**
     * @return array{type: string, loan_id: int, debtor_name: string, amount: string, due_on: string|null, message: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'loan_due',
            'loan_id' => (int) $this->loan->id,
            'debtor_name' => (string) $this->loan->debtor_name,
            'amount' => number_format((float) $this->loan->amount, 2, '.', ''),
            'due_on' => $this->dueOnString(),
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        $debtor = (string) $this->loan->debtor_name;
        $due = $this->dueOnString() ?? 'sem data';
        $amount = number_format((float) $this->loan->amount, 2, '.', '');

        return "Cobrar {$debtor} ({$amount} {$this->loan->currency}) até {$due}.";
    }

    private function dueOnString(): ?string
    {
        return $this->loan->due_on?->format('Y-m-d');
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
