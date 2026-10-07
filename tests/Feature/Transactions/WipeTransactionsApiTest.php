<?php

namespace Tests\Feature\Transactions;

use App\Http\Requests\Transactions\WipeTransactionsRequest;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WipeTransactionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/transactions/wipe', [
            'confirmation' => WipeTransactionsRequest::CONFIRMATION_PHRASE,
        ])->assertUnauthorized();
    }

    public function test_wipes_only_authenticated_user_transactions(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $ownerImport = StatementImport::factory()->for($owner)->create();
        $otherImport = StatementImport::factory()->for($other)->create();

        Transaction::factory()->count(3)->for($ownerImport, 'statementImport')->create([
            'user_id' => $owner->id,
        ]);
        Transaction::factory()->count(2)->for($otherImport, 'statementImport')->create([
            'user_id' => $other->id,
        ]);

        $this->actingAs($owner)
            ->postJson('/api/transactions/wipe', [
                'confirmation' => WipeTransactionsRequest::CONFIRMATION_PHRASE,
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 3)
            ->assertJsonPath('message', 'Histórico de transações excluído.');

        $this->assertSame(0, Transaction::query()->where('user_id', $owner->id)->count());
        $this->assertSame(2, Transaction::query()->where('user_id', $other->id)->count());
        $this->assertDatabaseHas('statement_imports', ['id' => $ownerImport->id]);
    }

    public function test_rejects_wrong_confirmation_phrase(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->postJson('/api/transactions/wipe', [
                'confirmation' => 'apagar tudo',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmation']);

        $this->actingAs($user)
            ->postJson('/api/transactions/wipe', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirmation']);
    }

    public function test_wipe_with_zero_transactions_returns_deleted_zero(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->postJson('/api/transactions/wipe', [
                'confirmation' => WipeTransactionsRequest::CONFIRMATION_PHRASE,
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 0);
    }

    public function test_count_returns_total_without_date_filter(): void
    {
        $user = User::factory()->visitor()->create();
        $import = StatementImport::factory()->for($user)->create();

        Transaction::factory()->count(2)->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'occurred_on' => '2025-01-10',
        ]);
        Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'occurred_on' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/transactions/count')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
    }
}
