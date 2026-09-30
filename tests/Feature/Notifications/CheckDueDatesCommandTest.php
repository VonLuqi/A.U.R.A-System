<?php

namespace Tests\Feature\Notifications;

use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\CreditCardDueNotification;
use App\Notifications\LoanDueNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §4.3 — aura:check-due-dates.
 */
class CheckDueDatesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Sao_Paulo']);
        config(['aura.features.notifications' => true]);
        config(['aura.notifications.credit_card_due_days' => 3]);
        config(['aura.notifications.loan_due_days' => 3]);
        config(['mail.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_feature_off_exits_zero_without_notifying(): void
    {
        config(['aura.features.notifications' => false]);
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'due_day' => 30,
            'is_active' => true,
        ]);

        $exit = Artisan::call('aura:check-due-dates');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('feature notifications is off', Artisan::output());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notifies_active_card_inside_window(): void
    {
        // Sep 29 → next due with due_day=1 is Oct 1 (within 3 days).
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nubank',
            'due_day' => 1,
            'is_active' => true,
        ]);

        $exit = Artisan::call('aura:check-due-dates');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('cards_notified=1', Artisan::output());
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(CreditCardDueNotification::class, $user->notifications()->first()?->type);
        $this->assertSame((int) $card->id, (int) $user->notifications()->first()?->data['credit_card_id']);
    }

    public function test_skips_card_outside_window_and_inactive_card(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        // due_day 15 → next is Oct 15 (outside 3-day window).
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'due_day' => 15,
            'is_active' => true,
        ]);
        CreditCard::factory()->inactive()->create([
            'user_id' => $user->id,
            'due_day' => 1,
        ]);

        Artisan::call('aura:check-due-dates');

        $this->assertStringContainsString('cards_notified=0', Artisan::output());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notifies_open_and_partial_loans_in_window_or_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        $inWindow = Loan::factory()->create([
            'user_id' => $user->id,
            'status' => LoanStatus::Open,
            'due_on' => '2026-10-01',
        ]);
        $overdue = Loan::factory()->create([
            'user_id' => $user->id,
            'status' => LoanStatus::Partial,
            'due_on' => '2026-09-20',
            'paid_amount' => '10.00',
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'status' => LoanStatus::Open,
            'due_on' => '2026-10-20', // outside window
        ]);
        Loan::factory()->paid()->create([
            'user_id' => $user->id,
            'due_on' => '2026-09-28',
        ]);

        Artisan::call('aura:check-due-dates');

        $this->assertStringContainsString('loans_notified=2', Artisan::output());
        $this->assertDatabaseCount('notifications', 2);

        $loanIds = $user->notifications()->get()->pluck('data.loan_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame([(int) $inWindow->id, (int) $overdue->id], $loanIds);
        $this->assertSame(LoanDueNotification::class, $user->notifications()->first()?->type);
    }

    public function test_skips_inactive_users(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $inactive = User::factory()->admin()->inactive()->create();
        CreditCard::factory()->create([
            'user_id' => $inactive->id,
            'due_day' => 1,
            'is_active' => true,
        ]);
        Loan::factory()->create([
            'user_id' => $inactive->id,
            'due_on' => '2026-10-01',
        ]);

        Artisan::call('aura:check-due-dates');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_second_run_same_day_counts_skipped_dupes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->admin()->create();
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'due_day' => 1,
            'is_active' => true,
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'due_on' => '2026-10-01',
        ]);

        Artisan::call('aura:check-due-dates');
        $this->assertDatabaseCount('notifications', 2);

        Artisan::call('aura:check-due-dates');
        $output = Artisan::output();

        $this->assertStringContainsString('cards_notified=0', $output);
        $this->assertStringContainsString('loans_notified=0', $output);
        $this->assertStringContainsString('skipped_dupes=2', $output);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_dry_run_does_not_persist_notifications(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-29 08:00:00', 'America/Sao_Paulo'));
        Notification::fake();

        $user = User::factory()->admin()->create();
        CreditCard::factory()->create([
            'user_id' => $user->id,
            'due_day' => 1,
            'is_active' => true,
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'due_on' => '2026-09-28',
        ]);

        $exit = Artisan::call('aura:check-due-dates', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry-run:', $output);
        $this->assertStringContainsString('cards_notified=1', $output);
        $this->assertStringContainsString('loans_notified=1', $output);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }
}
