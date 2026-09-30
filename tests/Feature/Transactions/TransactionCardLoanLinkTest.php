<?php

namespace Tests\Feature\Transactions;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §7.1 / §3.3 — vínculo + filtros credit_card/loan.
 */
class TransactionCardLoanLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_update_can_attach_and_detach_card_and_loan(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $tx = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx->id, [
                'credit_card_id' => $card->id,
                'loan_id' => $loan->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.credit_card.id', $card->id)
            ->assertJsonPath('data.loan.id', $loan->id);

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx->id, [
                'credit_card_id' => null,
                'loan_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.credit_card', null)
            ->assertJsonPath('data.loan', null);
    }

    public function test_index_filters_by_credit_card_loan_and_has_loan(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $linked = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'credit_card_id' => $card->id,
            'loan_id' => $loan->id,
            'description' => 'Com loan',
        ]);
        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'description' => 'Sem vínculo',
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions?credit_card_id='.$card->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $linked->id);

        $this->actingAs($user)
            ->getJson('/api/transactions?loan_id='.$loan->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $linked->id);

        $this->actingAs($user)
            ->getJson('/api/transactions?has_loan=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $linked->id);

        $this->actingAs($user)
            ->getJson('/api/transactions?has_loan=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Sem vínculo');

        $loan->refresh();
        $this->assertNotNull($loan->debtor_id);

        $this->actingAs($user)
            ->getJson('/api/transactions?debtor_id='.$loan->debtor_id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $linked->id);

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString().'&debtor_id='.$loan->debtor_id)
            ->assertOk()
            ->assertJsonPath('data.filters.debtor_id', $loan->debtor_id)
            ->assertJsonPath('data.cards.transactions_count', 1)
            ->assertJsonStructure([
                'data' => [
                    'hub' => [
                        'credit_cards' => ['active_count', 'period_spend'],
                        'loans' => ['open_count', 'remaining_total', 'overdue_count', 'debtors_with_open'],
                    ],
                ],
            ]);
    }

    public function test_linking_does_not_change_loan_paid_amount(): void
    {
        $user = User::factory()->admin()->create();
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'amount' => '200.00',
            'paid_amount' => '0.00',
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions', [
                'occurred_on' => now()->toDateString(),
                'amount' => '50.00',
                'type' => 'debit',
                'description' => 'Gasto emprestado',
                'loan_id' => $loan->id,
            ])
            ->assertCreated();

        $loan->refresh();
        $this->assertSame('0.00', $loan->paid_amount);
        $this->assertSame('open', $loan->status->value);
    }

    public function test_rejects_foreign_credit_card_and_loan_fks(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $foreignCard = CreditCard::factory()->create(['user_id' => $other->id]);
        $foreignLoan = Loan::factory()->create(['user_id' => $other->id]);

        $tx = Transaction::factory()->manual()->for($owner)->create([
            'occurred_on' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->patchJson('/api/transactions/'.$tx->id, [
                'credit_card_id' => $foreignCard->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_id']);

        $this->actingAs($owner)
            ->patchJson('/api/transactions/'.$tx->id, [
                'loan_id' => $foreignLoan->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan_id']);

        $this->actingAs($owner)
            ->postJson('/api/transactions', [
                'occurred_on' => now()->toDateString(),
                'amount' => '25.00',
                'type' => 'debit',
                'description' => 'FK estrangeira',
                'credit_card_id' => $foreignCard->id,
                'loan_id' => $foreignLoan->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_id', 'loan_id']);
    }
}
