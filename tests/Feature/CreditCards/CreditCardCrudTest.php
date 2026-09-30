<?php

namespace Tests\Feature\CreditCards;

use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_CARTOES_EMPRESTIMOS §7.1 / §3.1 — Credit cards CRUD API.
 */
class CreditCardCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        config(['aura.features.credit_cards' => true]);
    }

    public function test_credit_cards_require_authentication(): void
    {
        $this->getJson('/api/credit-cards')->assertUnauthorized();
        $this->postJson('/api/credit-cards')->assertUnauthorized();
    }

    public function test_crud_flow_and_open_loan_blocks_delete(): void
    {
        $user = User::factory()->admin()->create();

        $create = $this->actingAs($user)->postJson('/api/credit-cards', [
            'name' => 'Nubank Roxinho',
            'limit_amount' => '5000.00',
            'closing_day' => 5,
            'due_day' => 12,
            'last_four' => '4242',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Nubank Roxinho')
            ->assertJsonPath('data.closing_day', 5)
            ->assertJsonPath('data.due_day', 12)
            ->assertJsonPath('data.last_four', '4242')
            ->assertJsonStructure(['data' => ['next_due_on', 'next_closing_on', 'is_active']]);

        $id = (int) $create->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/credit-cards')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonStructure(['meta' => ['credit_cards_used', 'credit_cards_remaining']]);

        $this->actingAs($user)
            ->patchJson('/api/credit-cards/'.$id, [
                'name' => 'Nubank Ultravioleta',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nubank Ultravioleta')
            ->assertJsonPath('data.is_active', false);

        $card = CreditCard::query()->findOrFail($id);
        Loan::factory()->create([
            'user_id' => $user->id,
            'credit_card_id' => $card->id,
            'kind' => 'card_limit',
            'status' => LoanStatus::Open,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/credit-cards/'.$id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card']);

        Loan::query()->where('credit_card_id', $id)->update(['status' => LoanStatus::Paid]);

        $this->actingAs($user)
            ->deleteJson('/api/credit-cards/'.$id)
            ->assertOk()
            ->assertJsonPath('message', 'Cartão removido.');

        $this->assertDatabaseMissing('credit_cards', ['id' => $id]);
    }

    public function test_isolation_and_duplicate_name(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $card = CreditCard::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Meu Cartão',
        ]);

        $this->actingAs($other)
            ->getJson('/api/credit-cards/'.$card->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson('/api/credit-cards', [
                'name' => 'Meu Cartão',
                'closing_day' => 1,
                'due_day' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // Same name allowed for another user.
        $this->actingAs($other)
            ->postJson('/api/credit-cards', [
                'name' => 'Meu Cartão',
                'closing_day' => 1,
                'due_day' => 10,
            ])
            ->assertCreated();
    }

    public function test_closing_day_and_due_day_must_be_between_1_and_31(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->postJson('/api/credit-cards', [
                'name' => 'Inválido baixo',
                'closing_day' => 0,
                'due_day' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_day', 'due_day']);

        $this->actingAs($user)
            ->postJson('/api/credit-cards', [
                'name' => 'Inválido alto',
                'closing_day' => 32,
                'due_day' => 32,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing_day', 'due_day']);

        $this->actingAs($user)
            ->postJson('/api/credit-cards', [
                'name' => 'Limites ok',
                'closing_day' => 1,
                'due_day' => 31,
            ])
            ->assertCreated()
            ->assertJsonPath('data.closing_day', 1)
            ->assertJsonPath('data.due_day', 31);
    }

    public function test_feature_flag_blocks_access(): void
    {
        config(['aura.features.credit_cards' => false]);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/credit-cards')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature', 'credit_cards');
    }

    public function test_visitor_quota_blocks_create(): void
    {
        $visitor = User::factory()->visitor()->create();

        // Default visitor max_credit_cards = 3
        CreditCard::factory()->count(3)->create(['user_id' => $visitor->id]);

        $this->actingAs($visitor)
            ->postJson('/api/credit-cards', [
                'name' => 'Extra',
                'closing_day' => 2,
                'due_day' => 9,
            ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'usage_limit_exceeded');
    }

    public function test_first_card_becomes_default_and_switching_is_exclusive(): void
    {
        $user = User::factory()->admin()->create();

        $first = $this->actingAs($user)->postJson('/api/credit-cards', [
            'name' => 'Primeiro',
            'closing_day' => 1,
            'due_day' => 10,
        ])->assertCreated();

        $this->assertTrue($first->json('data.is_default'));
        $firstId = (int) $first->json('data.id');

        $second = $this->actingAs($user)->postJson('/api/credit-cards', [
            'name' => 'Segundo',
            'closing_day' => 5,
            'due_day' => 15,
            'is_default' => true,
        ])->assertCreated();

        $secondId = (int) $second->json('data.id');
        $this->assertTrue($second->json('data.is_default'));

        $this->assertDatabaseHas('credit_cards', [
            'id' => $firstId,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('credit_cards', [
            'id' => $secondId,
            'is_default' => true,
        ]);
    }
}
