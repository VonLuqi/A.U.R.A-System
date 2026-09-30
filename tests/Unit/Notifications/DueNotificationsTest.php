<?php

namespace Tests\Unit\Notifications;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\CreditCardDueNotification;
use App\Notifications\LoanDueNotification;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §4.1 — notification payloads and channels.
 */
class DueNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['aura.features.notifications' => true]);
        config(['mail.default' => 'array']);
    }

    public function test_credit_card_due_via_includes_database_and_mail_when_enabled(): void
    {
        $card = $this->makeCard();
        $notification = new CreditCardDueNotification($card);

        $this->assertSame(['database', 'mail'], $notification->via($this->makeUser()));
    }

    public function test_credit_card_due_via_skips_mail_when_feature_off(): void
    {
        config(['aura.features.notifications' => false]);

        $notification = new CreditCardDueNotification($this->makeCard());

        $this->assertSame(['database'], $notification->via($this->makeUser()));
    }

    public function test_credit_card_due_via_skips_mail_when_mailer_blank(): void
    {
        config(['mail.default' => '']);

        $notification = new CreditCardDueNotification($this->makeCard());

        $this->assertSame(['database'], $notification->via($this->makeUser()));
    }

    public function test_credit_card_due_to_array_payload(): void
    {
        $due = Carbon::parse('2026-10-05', 'America/Sao_Paulo')->startOfDay();
        $card = $this->makeCard(['id' => 42, 'name' => 'Nubank Roxinho']);
        $notification = new CreditCardDueNotification($card, $due);
        $payload = $notification->toArray($this->makeUser());

        $this->assertSame([
            'type' => 'credit_card_due',
            'credit_card_id' => 42,
            'name' => 'Nubank Roxinho',
            'due_on' => '2026-10-05',
            'message' => 'Fatura Nubank Roxinho vence em 2026-10-05.',
        ], $payload);
    }

    public function test_credit_card_due_to_mail_subject(): void
    {
        $due = Carbon::parse('2026-10-05', 'America/Sao_Paulo')->startOfDay();
        $card = $this->makeCard(['name' => 'Nubank Roxinho']);
        $mail = (new CreditCardDueNotification($card, $due))->toMail($this->makeUser());

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Fatura Nubank Roxinho vence em 2026-10-05', $mail->subject);
    }

    public function test_loan_due_via_includes_database_and_mail_when_enabled(): void
    {
        $notification = new LoanDueNotification($this->makeLoan());

        $this->assertSame(['database', 'mail'], $notification->via($this->makeUser()));
    }

    public function test_loan_due_to_array_payload(): void
    {
        $loan = $this->makeLoan([
            'id' => 7,
            'debtor_name' => 'Maria Silva',
            'amount' => '150.50',
            'currency' => 'BRL',
            'due_on' => '2026-10-12',
        ]);
        $payload = (new LoanDueNotification($loan))->toArray($this->makeUser());

        $this->assertSame([
            'type' => 'loan_due',
            'loan_id' => 7,
            'debtor_name' => 'Maria Silva',
            'amount' => '150.50',
            'due_on' => '2026-10-12',
            'message' => 'Cobrar Maria Silva (150.50 BRL) até 2026-10-12.',
        ], $payload);
    }

    public function test_loan_due_to_mail_subject(): void
    {
        $loan = $this->makeLoan([
            'debtor_name' => 'Maria Silva',
            'due_on' => '2026-10-12',
        ]);
        $mail = (new LoanDueNotification($loan))->toMail($this->makeUser());

        $this->assertInstanceOf(MailMessage::class, $mail);
        $this->assertSame('Cobrar Maria Silva até 2026-10-12', $mail->subject);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeCard(array $attrs = []): CreditCard
    {
        $card = new CreditCard(array_merge([
            'name' => 'Test Card',
            'currency' => 'BRL',
            'closing_day' => 1,
            'due_day' => 10,
            'is_active' => true,
        ], $attrs));

        if (isset($attrs['id'])) {
            $card->id = (int) $attrs['id'];
        }

        return $card;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeLoan(array $attrs = []): Loan
    {
        $loan = new Loan(array_merge([
            'debtor_name' => 'Devedor',
            'amount' => '100.00',
            'currency' => 'BRL',
            'due_on' => '2026-10-01',
        ], $attrs));

        if (isset($attrs['id'])) {
            $loan->id = (int) $attrs['id'];
        }

        return $loan;
    }

    private function makeUser(): User
    {
        return new User(['name' => 'Tester', 'email' => 't@example.com']);
    }
}
