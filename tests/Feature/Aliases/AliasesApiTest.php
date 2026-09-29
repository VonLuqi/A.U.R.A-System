<?php

namespace Tests\Feature\Aliases;

use App\Enums\AliasMatchType;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §4.2 — aliases API.
 */
class AliasesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_admin_crud_and_preview(): void
    {
        $user = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $created = $this->actingAs($user)
            ->postJson('/api/aliases', [
                'match_type' => AliasMatchType::Contains->value,
                'match_pattern' => 'IFOOD',
                'display_name' => 'iFood',
                'category_id' => $category->id,
                'priority' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'iFood')
            ->assertJsonPath('data.category.id', $category->id);

        $id = $created->json('data.id');

        $this->actingAs($user)
            ->getJson('/api/aliases')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $id);

        $this->actingAs($user)
            ->postJson('/api/aliases/preview', [
                'description' => 'Pg *IFOOD* 99',
            ])
            ->assertOk()
            ->assertJsonPath('data.alias_id', $id)
            ->assertJsonPath('data.display_name', 'iFood');

        $this->actingAs($user)
            ->patchJson('/api/aliases/'.$id, [
                'display_name' => 'Delivery',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Delivery')
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse(TransactionAlias::query()->findOrFail($id)->is_active);
        $this->assertNull(
            app(\App\Services\AliasResolutionService::class)->resolve($user, 'Pg *IFOOD* 99')
        );

        $this->actingAs($user)
            ->postJson('/api/aliases/preview', [
                'description' => 'Pg *IFOOD* 99',
            ])
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->actingAs($user)
            ->deleteJson('/api/aliases/'.$id)
            ->assertOk();

        $this->assertDatabaseMissing('transaction_aliases', ['id' => $id]);
    }

    public function test_visitor_can_create_until_alias_quota(): void
    {
        $visitor = User::factory()->visitor()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($visitor)
                ->postJson('/api/aliases', [
                    'match_type' => AliasMatchType::Contains->value,
                    'match_pattern' => 'PAT'.$i,
                    'display_name' => 'Name '.$i,
                ])
                ->assertCreated();
        }

        $this->actingAs($visitor)
            ->postJson('/api/aliases', [
                'match_type' => AliasMatchType::Contains->value,
                'match_pattern' => 'OVERFLOW',
                'display_name' => 'Too many',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'usage_limit_exceeded')
            ->assertJsonPath('metric', 'aliases')
            ->assertJsonPath('limit', 10)
            ->assertJsonPath('used', 10);
    }

    public function test_rejects_invalid_regex_pattern(): void
    {
        $user = User::factory()->subadmin()->create();

        $this->actingAs($user)
            ->postJson('/api/aliases', [
                'match_type' => AliasMatchType::Regex->value,
                'match_pattern' => '(unclosed',
                'display_name' => 'Bad',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['match_pattern']);
    }

    public function test_remember_alias_from_transaction(): void
    {
        $user = User::factory()->subadmin()->create();
        $tx = Transaction::factory()->manual()->for($user)->create([
            'description' => 'Uber *Trip City',
            'raw_payload' => [
                'origin' => 'manual',
                'original_description' => 'Uber *Trip City',
            ],
        ]);

        $this->actingAs($user)
            ->postJson('/api/transactions/'.$tx->id.'/remember-alias', [
                'display_name' => 'Uber',
                'match_type' => AliasMatchType::Contains->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Uber')
            ->assertJsonPath('data.match_pattern', 'Uber *Trip City')
            ->assertJsonPath('data.match_type', 'contains');
    }

    public function test_other_user_cannot_see_alias(): void
    {
        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $alias = TransactionAlias::factory()->for($owner)->create();

        $this->actingAs($other)
            ->getJson('/api/aliases/'.$alias->id)
            ->assertNotFound();
    }
}
