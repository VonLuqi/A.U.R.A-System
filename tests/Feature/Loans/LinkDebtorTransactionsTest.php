<?php

namespace Tests\Feature\Loans;

use App\Models\Debtor;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bulk link de saídas a pessoa (devedor).
 */
class LinkDebtorTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config(['aura.features.loans' => true]);
    }

    public function test_bulk_links_debits_reusing_or_creating_open_loan(): void
    {
        $user = User::factory()->admin()->create();
        $debtor = Debtor::factory()->create([
            'user_id' => $user->id,
            'name' => 'João',
        ]);

        $debitA = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'amount' => '40.00',
            'description' => 'Almoço',
            'loan_id' => null,
        ]);
        $debitB = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'amount' => '25.00',
            'description' => 'Café',
            'loan_id' => null,
        ]);
        $credit = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'credit',
            'description' => 'Pix',
            'loan_id' => null,
        ]);

        $response = $this->actingAs($user)->postJson(
            '/api/debtors/'.$debtor->id.'/link-transactions',
            [
                'transaction_ids' => [$debitA->id, $debitB->id, $credit->id, 999999],
            ],
        );

        $response->assertOk()
            ->assertJsonPath('data.linked', 2)
            ->assertJsonPath('data.skipped', 2)
            ->assertJsonPath('message', '2 saídas vinculadas à pessoa.');

        $debitA->refresh();
        $debitB->refresh();

        $this->assertNotNull($debitA->loan_id);
        $this->assertSame((int) $debitA->loan_id, (int) $debitB->loan_id);

        $loan = Loan::query()->findOrFail($debitA->loan_id);
        $this->assertSame($debtor->id, (int) $loan->debtor_id);
        $this->assertSame('João', $loan->debtor_name);
        $this->assertSame('open', $loan->status->value);

        $this->assertDatabaseHas('transactions', [
            'id' => $credit->id,
            'loan_id' => null,
        ]);
    }

    public function test_skips_already_linked_to_same_debtor_and_reassigns_others(): void
    {
        $user = User::factory()->admin()->create();
        $debtor = Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Ana']);
        $other = Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Bruno']);

        $ownLoan = Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtor->id,
            'debtor_name' => 'Ana',
            'status' => 'open',
        ]);
        $otherLoan = Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $other->id,
            'debtor_name' => 'Bruno',
            'status' => 'open',
        ]);

        $already = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'loan_id' => $ownLoan->id,
        ]);
        $fromOther = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => now()->toDateString(),
            'type' => 'debit',
            'loan_id' => $otherLoan->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/debtors/'.$debtor->id.'/link-transactions', [
                'transaction_ids' => [$already->id, $fromOther->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.linked', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertDatabaseHas('transactions', [
            'id' => $already->id,
            'loan_id' => $ownLoan->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $fromOther->id,
            'loan_id' => $ownLoan->id,
        ]);
    }

    public function test_rejects_empty_selection_and_foreign_debtor(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $foreign = Debtor::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)
            ->postJson('/api/debtors/'.$foreign->id.'/link-transactions', [
                'transaction_ids' => [1],
            ])
            ->assertNotFound();

        $debtor = Debtor::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson('/api/debtors/'.$debtor->id.'/link-transactions', [
                'transaction_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_ids']);
    }
}
