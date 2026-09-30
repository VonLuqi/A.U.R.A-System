<?php

namespace Tests\Feature\CreditCards;

use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk link de saídas a cartão + filtro has_credit_card.
 */
class LinkCreditCardTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config(['aura.features.credit_cards' => true]);
    }

    public function test_bulk_links_debits_and_skips_credits_and_already_linked(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);
        $otherCard = CreditCard::factory()->create(['user_id' => $user->id]);

        $debitA = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'description' => 'Mercado',
            'credit_card_id' => null,
        ]);
        $debitB = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'description' => 'Farmácia',
            'credit_card_id' => null,
        ]);
        $credit = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'credit',
            'description' => 'Salário',
            'credit_card_id' => null,
        ]);
        $already = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'description' => 'Já no cartão',
            'credit_card_id' => $card->id,
        ]);
        $otherLinked = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'description' => 'Outro cartão',
            'credit_card_id' => $otherCard->id,
        ]);

        $response = $this->actingAs($user)->postJson(
            '/api/credit-cards/'.$card->id.'/link-transactions',
            [
                'transaction_ids' => [
                    $debitA->id,
                    $debitB->id,
                    $credit->id,
                    $already->id,
                    $otherLinked->id,
                    999999,
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.linked', 3)
            ->assertJsonPath('data.skipped', 3)
            ->assertJsonPath('message', '3 saídas vinculadas ao cartão.');

        $this->assertDatabaseHas('transactions', [
            'id' => $debitA->id,
            'credit_card_id' => $card->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $debitB->id,
            'credit_card_id' => $card->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $otherLinked->id,
            'credit_card_id' => $card->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $credit->id,
            'credit_card_id' => null,
        ]);
    }

    public function test_has_credit_card_filter_lists_unlinked_debits(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $unlinked = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'description' => 'Sem cartão',
            'credit_card_id' => null,
        ]);
        Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'description' => 'Com cartão',
            'credit_card_id' => $card->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions?type=debit&has_credit_card=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $unlinked->id);

        $this->actingAs($user)
            ->getJson('/api/transactions?type=debit&has_credit_card=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_rejects_empty_selection_and_foreign_card(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $foreignCard = CreditCard::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)
            ->postJson('/api/credit-cards/'.$foreignCard->id.'/link-transactions', [
                'transaction_ids' => [1],
            ])
            ->assertNotFound();

        $card = CreditCard::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson('/api/credit-cards/'.$card->id.'/link-transactions', [
                'transaction_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_ids']);
    }
}
