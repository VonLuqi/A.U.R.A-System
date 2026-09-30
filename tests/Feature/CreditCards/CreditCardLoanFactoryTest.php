<?php

namespace Tests\Feature\CreditCards;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\DemoCreditCardsAndLoansSeeder;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §1.6 — factories + demo seeder.
 */
class CreditCardLoanFactoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_credit_card_factory_and_inactive_state(): void
    {
        $card = CreditCard::factory()->inactive()->create();

        $this->assertFalse($card->is_active);
        $this->assertDatabaseHas('credit_cards', [
            'id' => $card->id,
            'is_active' => 0,
        ]);
    }

    public function test_loan_factory_states_paid_overdue_and_card_limit(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $paid = Loan::factory()->paid()->create(['user_id' => $user->id, 'amount' => '200.00']);
        $this->assertSame(LoanStatus::Paid, $paid->status);
        $this->assertSame('200.00', $paid->paid_amount);
        $this->assertNotNull($paid->paid_at);

        $overdue = Loan::factory()->overdue()->create(['user_id' => $user->id]);
        $this->assertSame(LoanStatus::Open, $overdue->status);
        $this->assertTrue($overdue->due_on->lt(now()->startOfDay()));

        $onCard = Loan::factory()->cardLimit($card)->create([
            'user_id' => $user->id,
            'amount' => '99.90',
        ]);
        $this->assertSame(LoanKind::CardLimit, $onCard->kind);
        $this->assertSame($card->id, $onCard->credit_card_id);
        $this->assertSame($user->id, $onCard->user_id);
    }

    public function test_demo_credit_cards_and_loans_seeder_is_idempotent_locally(): void
    {
        $this->seed(DemoCreditCardsAndLoansSeeder::class);
        $this->seed(DemoCreditCardsAndLoansSeeder::class);

        $admin = User::query()->where('email', env('ADMIN_EMAIL', 'admin@aura.local'))->first();
        $this->assertNotNull($admin);
        $this->assertSame(3, CreditCard::query()->where('user_id', $admin->id)->count());
        $this->assertSame(4, Loan::query()->where('user_id', $admin->id)->count());
    }
}
