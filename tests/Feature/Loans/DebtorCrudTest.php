<?php

namespace Tests\Feature\Loans;

use App\Enums\LoanKind;
use App\Models\CreditCard;
use App\Models\Debtor;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtorCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config([
            'aura.features.loans' => true,
            'aura.features.credit_cards' => true,
            'aura.features.manual_transactions' => true,
        ]);
    }

    public function test_can_create_person_before_loan(): void
    {
        $user = User::factory()->admin()->create();

        $create = $this->actingAs($user)->postJson('/api/debtors', [
            'name' => 'Geraldo',
            'notes' => 'Pega limite no cartão',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Geraldo');

        $debtorId = (int) $create->json('data.id');

        $loan = $this->actingAs($user)->postJson('/api/loans', [
            'debtor_id' => $debtorId,
            'kind' => LoanKind::Cash->value,
            'amount' => '80.00',
            'lent_on' => '2026-09-29',
            'due_on' => '2026-10-10',
        ]);

        $loan->assertCreated()
            ->assertJsonPath('data.debtor_id', $debtorId)
            ->assertJsonPath('data.debtor_name', 'Geraldo');
    }

    public function test_assign_person_on_card_expense_creates_or_reuses_loan(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);
        $debtor = Debtor::factory()->create([
            'user_id' => $user->id,
            'name' => 'Geraldo',
        ]);

        $tx = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-29',
            'amount' => '45.50',
            'type' => 'debit',
            'description' => 'Uber',
            'credit_card_id' => $card->id,
            'loan_id' => null,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx->id, [
                'debtor_id' => $debtor->id,
                'credit_card_id' => $card->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.loan.debtor_name', 'Geraldo');

        $tx->refresh();
        $this->assertNotNull($tx->loan_id);

        $loan = Loan::query()->findOrFail($tx->loan_id);
        $this->assertSame($debtor->id, (int) $loan->debtor_id);
        $this->assertSame('45.50', $loan->amount);
        $this->assertSame(LoanKind::CardLimit, $loan->kind);
        $this->assertSame((int) $card->id, (int) $loan->credit_card_id);

        // Second expense reuses open loan
        $tx2 = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-30',
            'amount' => '10.00',
            'type' => 'debit',
            'description' => 'Padaria',
            'credit_card_id' => $card->id,
        ]);

        $this->actingAs($user)
            ->patchJson('/api/transactions/'.$tx2->id, [
                'debtor_id' => $debtor->id,
            ])
            ->assertOk();

        $tx2->refresh();
        $this->assertSame((int) $loan->id, (int) $tx2->loan_id);
        $this->assertSame(1, Loan::query()->where('debtor_id', $debtor->id)->count());
    }

    public function test_index_exposes_open_remaining_total(): void
    {
        $user = User::factory()->admin()->create();
        $debtor = Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Luca']);

        Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtor->id,
            'debtor_name' => 'Luca',
            'status' => 'open',
            'amount' => '100.00',
            'paid_amount' => '20.00',
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtor->id,
            'debtor_name' => 'Luca',
            'status' => 'paid',
            'amount' => '50.00',
            'paid_amount' => '50.00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/debtors')
            ->assertOk()
            ->assertJsonPath('data.0.id', $debtor->id)
            ->assertJsonPath('data.0.open_loans_count', 1)
            ->assertJsonPath('data.0.open_remaining_total', '80.00');
    }
}
