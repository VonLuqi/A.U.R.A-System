<?php

namespace Tests\Feature\Aliases;

use App\Enums\AliasMatchType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AliasRetroactiveApplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §4.3 — sync retroactive apply on alias create.
 */
class AliasRetroactiveApplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_with_apply_to_existing_updates_matching_transactions(): void
    {
        $user = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $match = Transaction::factory()->manual()->for($user)->create([
            'description' => 'Pg *IFOOD* Pedido',
            'category_id' => null,
            'occurred_on' => '2026-09-20',
            'raw_payload' => [
                'origin' => 'manual',
                'original_description' => 'Pg *IFOOD* Pedido',
            ],
        ]);

        $other = Transaction::factory()->manual()->for($user)->create([
            'description' => 'Uber *Trip',
            'category_id' => null,
            'occurred_on' => '2026-09-19',
            'raw_payload' => [
                'origin' => 'manual',
                'original_description' => 'Uber *Trip',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/aliases', [
                'match_type' => AliasMatchType::Contains->value,
                'match_pattern' => 'IFOOD',
                'display_name' => 'iFood',
                'category_id' => $category->id,
                'apply_to_existing' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'iFood')
            ->assertJsonPath('retroactive.updated', 1)
            ->assertJsonPath('retroactive.scanned', 2)
            ->assertJsonPath('retroactive.limit', 500);

        $match->refresh();
        $other->refresh();

        $this->assertSame('iFood', $match->description);
        $this->assertSame($category->id, $match->category_id);
        $this->assertSame('Pg *IFOOD* Pedido', $match->raw_payload['original_description']);
        $this->assertSame($response->json('data.id'), $match->raw_payload['alias_id']);

        $this->assertSame('Uber *Trip', $other->description);
        $this->assertNull($other->category_id);
    }

    public function test_retroactive_respects_limit_of_most_recent_rows(): void
    {
        config(['aura.aliases.retroactive_limit' => 2]);

        $user = User::factory()->admin()->create();

        // Older matching row — outside the limit window of 2 most recent.
        Transaction::factory()->manual()->for($user)->create([
            'description' => 'IFOOD old',
            'occurred_on' => '2026-01-01',
            'raw_payload' => ['original_description' => 'IFOOD old'],
        ]);

        Transaction::factory()->manual()->for($user)->create([
            'description' => 'Unrelated',
            'occurred_on' => '2026-09-10',
            'raw_payload' => ['original_description' => 'Unrelated'],
        ]);

        $recentMatch = Transaction::factory()->manual()->for($user)->create([
            'description' => 'IFOOD recent',
            'occurred_on' => '2026-09-20',
            'raw_payload' => ['original_description' => 'IFOOD recent'],
        ]);

        $this->actingAs($user)
            ->postJson('/api/aliases', [
                'match_type' => AliasMatchType::Contains->value,
                'match_pattern' => 'IFOOD',
                'display_name' => 'iFood',
                'apply_to_existing' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('retroactive.limit', 2)
            ->assertJsonPath('retroactive.scanned', 2)
            ->assertJsonPath('retroactive.updated', 1);

        $this->assertSame('iFood', $recentMatch->fresh()->description);
        $this->assertDatabaseHas('transactions', [
            'description' => 'IFOOD old',
        ]);
    }

    public function test_service_matches_description_when_original_missing(): void
    {
        $user = User::factory()->create();
        $alias = \App\Models\TransactionAlias::factory()->for($user)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'RAIA',
            'display_name' => 'Farmácia',
            'category_id' => null,
        ]);

        $tx = Transaction::factory()->manual()->for($user)->create([
            'description' => 'DROGA RAIA',
            'raw_payload' => ['origin' => 'manual'],
        ]);

        $result = app(AliasRetroactiveApplyService::class)->apply($alias, 10);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('Farmácia', $tx->fresh()->description);
        $this->assertSame('DROGA RAIA', $tx->fresh()->raw_payload['original_description']);
    }

    public function test_store_without_flag_does_not_rewrite_transactions(): void
    {
        $user = User::factory()->admin()->create();
        $tx = Transaction::factory()->manual()->for($user)->create([
            'description' => 'IFOOD raw',
            'raw_payload' => ['original_description' => 'IFOOD raw'],
        ]);

        $this->actingAs($user)
            ->postJson('/api/aliases', [
                'match_type' => AliasMatchType::Contains->value,
                'match_pattern' => 'IFOOD',
                'display_name' => 'iFood',
            ])
            ->assertCreated()
            ->assertJsonMissingPath('retroactive');

        $this->assertSame('IFOOD raw', $tx->fresh()->description);
    }
}
