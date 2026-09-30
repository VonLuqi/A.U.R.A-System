<?php

namespace Tests\Feature\CreditCards;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §2.3 — policies + ownership on transaction FKs.
 */
class CreditCardLoanPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config([
            'aura.features.credit_cards' => true,
            'aura.features.loans' => true,
        ]);
    }

    public function test_owner_can_manage_own_credit_card_and_loan(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->visitor()->create();

        $card = CreditCard::factory()->create(['user_id' => $owner->id]);
        $loan = Loan::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($owner->can('viewAny', CreditCard::class));
        $this->assertTrue($owner->can('view', $card));
        $this->assertTrue($owner->can('update', $card));
        $this->assertTrue($owner->can('delete', $card));

        $this->assertTrue($owner->can('viewAny', Loan::class));
        $this->assertTrue($owner->can('view', $loan));
        $this->assertTrue($owner->can('update', $loan));

        $this->assertFalse($other->can('view', $card));
        $this->assertFalse($other->can('update', $loan));
    }

    public function test_feature_flag_denies_credit_cards_manage(): void
    {
        config(['aura.features.credit_cards' => false]);

        $admin = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $admin->id]);

        $this->assertFalse($admin->can('viewAny', CreditCard::class));
        $this->assertFalse($admin->can('update', $card));
    }

    public function test_store_transaction_rejects_foreign_credit_card_and_loan(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $foreignCard = CreditCard::factory()->create(['user_id' => $other->id]);
        $foreignLoan = Loan::factory()->create(['user_id' => $other->id]);
        $ownCard = CreditCard::factory()->create(['user_id' => $owner->id]);
        $ownLoan = Loan::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-01',
                'amount' => '10.00',
                'type' => 'debit',
                'description' => 'Compra teste',
                'credit_card_id' => $foreignCard->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_id']);

        $this->actingAs($owner)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-01',
                'amount' => '10.00',
                'type' => 'debit',
                'description' => 'Compra teste 2',
                'loan_id' => $foreignLoan->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan_id']);

        $this->actingAs($owner)
            ->postJson('/api/transactions', [
                'occurred_on' => '2026-09-01',
                'amount' => '25.50',
                'type' => 'debit',
                'description' => 'No cartão para pessoa',
                'credit_card_id' => $ownCard->id,
                'loan_id' => $ownLoan->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.credit_card.id', $ownCard->id)
            ->assertJsonPath('data.loan.id', $ownLoan->id)
            ->assertJsonPath('data.loan.debtor_name', $ownLoan->debtor_name);
    }
}
