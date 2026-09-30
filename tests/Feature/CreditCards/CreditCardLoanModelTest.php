<?php

namespace Tests\Feature\CreditCards;

use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §2.2 — CreditCard / Loan domain helpers + Transaction FKs.
 */
class CreditCardLoanModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_credit_card_scopes_and_next_dates(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->visitor()->create();

        $active = CreditCard::factory()->create([
            'user_id' => $owner->id,
            'due_day' => 15,
            'closing_day' => 5,
            'is_active' => true,
        ]);
        CreditCard::factory()->inactive()->create(['user_id' => $owner->id]);
        CreditCard::factory()->create(['user_id' => $other->id]);

        $this->assertSame([$active->id], CreditCard::query()->forUser($owner)->active()->pluck('id')->all());

        Carbon::setTestNow(Carbon::parse('2026-09-10', 'America/Sao_Paulo'));
        $this->assertSame('2026-09-15', $active->nextDueDate()->toDateString());
        $this->assertSame('2026-10-05', $active->nextClosingDate()->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-09-15', 'America/Sao_Paulo'));
        $this->assertSame('2026-09-15', $active->nextDueDate()->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-09-16', 'America/Sao_Paulo'));
        $this->assertSame('2026-10-15', $active->nextDueDate()->toDateString());

        // Day 31 clamped in February.
        $febCard = CreditCard::factory()->create([
            'user_id' => $owner->id,
            'due_day' => 31,
            'closing_day' => 31,
        ]);
        Carbon::setTestNow(Carbon::parse('2026-02-01', 'America/Sao_Paulo'));
        $this->assertSame('2026-02-28', $febCard->nextDueDate()->toDateString());

        Carbon::setTestNow();
    }

    public function test_loan_scopes_mark_paid_and_overdue(): void
    {
        $user = User::factory()->admin()->create();

        $open = Loan::factory()->create([
            'user_id' => $user->id,
            'amount' => '100.00',
            'paid_amount' => '0.00',
            'due_on' => now()->addDays(3)->toDateString(),
        ]);
        $overdue = Loan::factory()->overdue()->create([
            'user_id' => $user->id,
            'amount' => '50.00',
        ]);
        Loan::factory()->paid()->create(['user_id' => $user->id]);

        $this->assertSame(2, Loan::query()->forUser($user)->open()->count());
        $this->assertTrue($overdue->fresh()->isOverdue());
        $this->assertFalse($open->fresh()->isOverdue());

        $from = now()->toDateString();
        $to = now()->addDays(7)->toDateString();
        $this->assertTrue(
            Loan::query()->forUser($user)->dueBetween($from, $to)->whereKey($open->id)->exists()
        );

        $open->markPaid(40.0);
        $open->refresh();
        $this->assertSame(LoanStatus::Partial, $open->status);
        $this->assertSame(60.0, $open->remainingAmount());

        $open->markPaid();
        $open->refresh();
        $this->assertSame(LoanStatus::Paid, $open->status);
        $this->assertSame(0.0, $open->remainingAmount());
        $this->assertNotNull($open->paid_at);
        $this->assertFalse($open->isOverdue());
    }

    public function test_transaction_belongs_to_credit_card_and_loan(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->cardLimit($card)->create(['user_id' => $user->id]);

        $tx = Transaction::factory()->create([
            'user_id' => $user->id,
            'credit_card_id' => $card->id,
            'loan_id' => $loan->id,
        ]);

        $tx->load(['creditCard', 'loan']);

        $this->assertSame($card->id, $tx->creditCard->id);
        $this->assertSame($loan->id, $tx->loan->id);
        $this->assertTrue($card->transactions()->whereKey($tx->id)->exists());
        $this->assertTrue($loan->transactions()->whereKey($tx->id)->exists());
    }
}
