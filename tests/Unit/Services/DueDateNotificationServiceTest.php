<?php

namespace Tests\Unit\Services;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\CreditCardDueNotification;
use App\Notifications\LoanDueNotification;
use App\Services\DueDateNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §4.2 — due-date notification deduplication.
 */
class DueDateNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DueDateNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Sao_Paulo']);
        config(['aura.features.notifications' => true]);
        config(['mail.default' => 'array']);

        $this->service = app(DueDateNotificationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_notify_credit_card_persists_database_notification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nubank',
            'due_day' => 1,
        ]);

        $result = $this->service->notifyCreditCardDue($user, $card);

        $this->assertSame(DueDateNotificationService::RESULT_NOTIFIED, $result);
        $this->assertDatabaseCount('notifications', 1);

        $row = $user->notifications()->first();
        $this->assertNotNull($row);
        $this->assertSame(CreditCardDueNotification::class, $row->type);
        $this->assertSame('credit_card_due', $row->data['type']);
        $this->assertSame((int) $card->id, (int) $row->data['credit_card_id']);
    }

    public function test_second_notify_same_day_is_skipped_as_dupe(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);

        $this->assertSame(
            DueDateNotificationService::RESULT_NOTIFIED,
            $this->service->notifyCreditCardDue($user, $card),
        );
        $this->assertSame(
            DueDateNotificationService::RESULT_SKIPPED_DUPE,
            $this->service->notifyCreditCardDue($user, $card),
        );
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_unread_notification_blocks_even_after_calendar_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);

        $this->service->notifyCreditCardDue($user, $card);
        $this->assertDatabaseCount('notifications', 1);

        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $this->assertSame(
            DueDateNotificationService::RESULT_SKIPPED_DUPE,
            $this->service->notifyCreditCardDue($user, $card),
        );
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_read_notification_from_previous_day_allows_new_notify(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);

        $this->service->notifyCreditCardDue($user, $card);
        $user->notifications()->first()?->markAsRead();

        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $this->assertSame(
            DueDateNotificationService::RESULT_NOTIFIED,
            $this->service->notifyCreditCardDue($user, $card),
        );
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_different_cards_are_not_deduped_together(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $a = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);
        $b = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 5]);

        $this->assertSame(
            DueDateNotificationService::RESULT_NOTIFIED,
            $this->service->notifyCreditCardDue($user, $a),
        );
        $this->assertSame(
            DueDateNotificationService::RESULT_NOTIFIED,
            $this->service->notifyCreditCardDue($user, $b),
        );
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_notify_loan_dedupes_by_loan_id(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_name' => 'Maria',
            'due_on' => '2026-10-01',
        ]);

        $this->assertSame(
            DueDateNotificationService::RESULT_NOTIFIED,
            $this->service->notifyLoanDue($user, $loan),
        );
        $this->assertSame(
            DueDateNotificationService::RESULT_SKIPPED_DUPE,
            $this->service->notifyLoanDue($user, $loan),
        );

        $row = $user->notifications()->first();
        $this->assertSame(LoanDueNotification::class, $row?->type);
        $this->assertSame('loan_due', $row?->data['type']);
        $this->assertSame((int) $loan->id, (int) $row?->data['loan_id']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_dry_run_does_not_persist_but_reports_notified(): void
    {
        Notification::fake();

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);

        $result = $this->service->notifyCreditCardDue($user, $card, dryRun: true);

        $this->assertSame(DueDateNotificationService::RESULT_NOTIFIED, $result);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_dry_run_still_reports_dupe_when_already_notified(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'due_day' => 1]);

        $this->service->notifyCreditCardDue($user, $card);

        $result = $this->service->notifyCreditCardDue($user, $card, dryRun: true);

        $this->assertSame(DueDateNotificationService::RESULT_SKIPPED_DUPE, $result);
        $this->assertDatabaseCount('notifications', 1);
    }
}
