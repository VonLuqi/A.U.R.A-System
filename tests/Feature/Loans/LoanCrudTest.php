<?php

namespace Tests\Feature\Loans;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §7.1 / §3.2 — Loans CRUD API.
 */
class LoanCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config([
            'aura.features.loans' => true,
            'aura.features.credit_cards' => true,
        ]);
    }

    public function test_loans_require_authentication(): void
    {
        $this->getJson('/api/loans')->assertUnauthorized();
        $this->postJson('/api/loans')->assertUnauthorized();
    }

    public function test_crud_mark_paid_cancel_and_delete_rules(): void
    {
        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $createCash = $this->actingAs($user)->postJson('/api/loans', [
            'debtor_name' => 'João Silva',
            'kind' => LoanKind::Cash->value,
            'amount' => '100.00',
            'lent_on' => '2026-09-01',
            'due_on' => '2026-09-15',
        ]);

        $createCash->assertCreated()
            ->assertJsonPath('data.debtor_name', 'João Silva')
            ->assertJsonPath('data.kind', 'cash')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.remaining_amount', '100.00')
            ->assertJsonPath('data.credit_card_id', null);

        $cashId = (int) $createCash->json('data.id');

        $createCard = $this->actingAs($user)->postJson('/api/loans', [
            'debtor_name' => 'Maria Souza',
            'kind' => LoanKind::CardLimit->value,
            'credit_card_id' => $card->id,
            'amount' => '80.00',
            'lent_on' => '2026-09-01',
            'due_on' => '2026-09-20',
        ]);

        $createCard->assertCreated()
            ->assertJsonPath('data.kind', 'card_limit')
            ->assertJsonPath('data.credit_card.id', $card->id);

        $cardLoanId = (int) $createCard->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/loans')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['meta' => ['loans_used', 'loans_remaining']]);

        $this->actingAs($user)
            ->postJson('/api/loans/'.$cashId.'/mark-paid', ['paid_amount' => 40])
            ->assertOk()
            ->assertJsonPath('data.status', LoanStatus::Partial->value)
            ->assertJsonPath('data.remaining_amount', '60.00');

        $paid = $this->actingAs($user)
            ->postJson('/api/loans/'.$cashId.'/mark-paid')
            ->assertOk()
            ->assertJsonPath('data.status', LoanStatus::Paid->value)
            ->assertJsonPath('data.remaining_amount', '0.00');

        $this->assertNotNull($paid->json('data.paid_at'));
        $this->assertDatabaseHas('loans', [
            'id' => $cashId,
            'status' => LoanStatus::Paid->value,
        ]);
        $this->assertNotNull(Loan::query()->findOrFail($cashId)->paid_at);

        $this->actingAs($user)
            ->deleteJson('/api/loans/'.$cashId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan']);

        $this->actingAs($user)
            ->postJson('/api/loans/'.$cardLoanId.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', LoanStatus::Cancelled->value);

        $this->actingAs($user)
            ->deleteJson('/api/loans/'.$cardLoanId)
            ->assertOk()
            ->assertJsonPath('message', 'Cobrança removida.');
    }

    public function test_card_limit_requires_owned_card_and_blocks_foreign(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $foreignCard = CreditCard::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)
            ->postJson('/api/loans', [
                'debtor_name' => 'X',
                'kind' => LoanKind::CardLimit->value,
                'amount' => '10.00',
                'lent_on' => '2026-09-01',
                'due_on' => '2026-09-10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_id']);

        $this->actingAs($owner)
            ->postJson('/api/loans', [
                'debtor_name' => 'X',
                'kind' => LoanKind::CardLimit->value,
                'credit_card_id' => $foreignCard->id,
                'amount' => '10.00',
                'lent_on' => '2026-09-01',
                'due_on' => '2026-09-10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_id']);
    }

    public function test_delete_blocked_when_transactions_linked(): void
    {
        $user = User::factory()->admin()->create();
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'status' => LoanStatus::Open,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'loan_id' => $loan->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/loans/'.$loan->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan']);

        $this->actingAs($user)
            ->postJson('/api/loans/'.$loan->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_overdue_filter_and_isolation(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $overdue = Loan::factory()->overdue()->create([
            'user_id' => $owner->id,
            'debtor_name' => 'Atrasado',
        ]);
        Loan::factory()->create([
            'user_id' => $owner->id,
            'debtor_name' => 'Futuro',
            'due_on' => now()->addDays(10)->toDateString(),
        ]);
        Loan::factory()->create(['user_id' => $other->id]);

        $this->actingAs($owner)
            ->getJson('/api/loans?overdue=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $overdue->id);

        $this->actingAs($other)
            ->getJson('/api/loans/'.$overdue->id)
            ->assertNotFound();
    }

    public function test_feature_flag_blocks_access(): void
    {
        config(['aura.features.loans' => false]);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/loans')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature', 'loans');
    }

    public function test_create_can_register_linked_expense_without_marking_paid(): void
    {
        config(['aura.features.manual_transactions' => true]);

        $user = User::factory()->admin()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/loans', [
            'debtor_name' => 'Carla',
            'kind' => LoanKind::CardLimit->value,
            'credit_card_id' => $card->id,
            'amount' => '150.00',
            'lent_on' => '2026-09-10',
            'due_on' => '2026-09-25',
            'create_expense' => true,
            'expense_description' => 'Mercado Extra',
            'expense_amount' => '149.90',
            'expense_occurred_on' => '2026-09-10',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.debtor_name', 'Carla')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.paid_amount', '0.00');

        $loanId = (int) $response->json('data.id');

        $expense = Transaction::query()
            ->where('user_id', $user->id)
            ->where('loan_id', $loanId)
            ->firstOrFail();

        $this->assertSame('debit', $expense->type);
        $this->assertSame('Mercado Extra', $expense->description);
        $this->assertSame('149.90', $expense->amount);
        $this->assertSame((int) $card->id, (int) $expense->credit_card_id);
        $this->assertSame('2026-09-10', $expense->occurred_on->format('Y-m-d'));
        $this->assertSame('0.00', Loan::query()->findOrFail($loanId)->paid_amount);
    }

    public function test_create_expense_requires_description(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->postJson('/api/loans', [
            'debtor_name' => 'Sem descrição',
            'kind' => LoanKind::Cash->value,
            'amount' => '50.00',
            'lent_on' => '2026-09-10',
            'due_on' => '2026-09-20',
            'create_expense' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expense_description']);
    }

    public function test_index_filters_by_debtor_id_and_collectible(): void
    {
        $user = User::factory()->admin()->create();
        $debtorA = \App\Models\Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Ana']);
        $debtorB = \App\Models\Debtor::factory()->create(['user_id' => $user->id, 'name' => 'Bruno']);

        $openA = Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtorA->id,
            'debtor_name' => 'Ana',
            'status' => LoanStatus::Open,
            'due_on' => '2026-10-01',
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtorA->id,
            'debtor_name' => 'Ana',
            'status' => LoanStatus::Paid,
            'due_on' => '2026-09-01',
        ]);
        Loan::factory()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtorB->id,
            'debtor_name' => 'Bruno',
            'status' => LoanStatus::Open,
            'due_on' => '2026-10-05',
        ]);

        $this->actingAs($user)
            ->getJson('/api/loans?debtor_id='.$debtorA->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAs($user)
            ->getJson('/api/loans?debtor_id='.$debtorA->id.'&collectible=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $openA->id);

        $this->actingAs($user)
            ->getJson('/api/loans?collectible=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }
}
