<?php

namespace Tests\Feature\Aliases;

use App\Enums\AliasMatchType;
use App\Enums\UserRole;
use App\Models\TransactionAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionAliasModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_alias_for_user(): void
    {
        $user = User::factory()->admin()->create();
        $alias = TransactionAlias::factory()->for($user)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'IFOOD',
            'display_name' => 'iFood',
            'priority' => 10,
        ]);

        $this->assertSame($user->id, $alias->user_id);
        $this->assertSame(AliasMatchType::Contains, $alias->match_type);
        $this->assertTrue($alias->is_active);
        $this->assertTrue($user->transactionAliases()->whereKey($alias->id)->exists());
    }

    public function test_matches_supports_all_match_types(): void
    {
        $exact = TransactionAlias::factory()->exact()->make([
            'match_pattern' => 'Uber Trip',
        ]);
        $contains = TransactionAlias::factory()->contains()->make([
            'match_pattern' => 'IFOOD',
        ]);
        $starts = TransactionAlias::factory()->startsWith()->make([
            'match_pattern' => 'NU PAG',
        ]);
        $regex = TransactionAlias::factory()->regex()->make([
            'match_pattern' => 'uber|99\\s*app',
        ]);

        $this->assertTrue($exact->matches('uber trip'));
        $this->assertFalse($exact->matches('Uber Trip extra'));

        $this->assertTrue($contains->matches('Pg *IFOOD* Pedido'));
        $this->assertFalse($contains->matches('Rappi'));

        $this->assertTrue($starts->matches('NU PAGAMENTOS S.A.'));
        $this->assertFalse($starts->matches('PAG NU'));

        $this->assertTrue($regex->matches('99 app corrida'));
        $this->assertFalse($regex->matches('taxi amarelo'));
    }

    public function test_scope_for_resolution_orders_by_priority_and_skips_inactive(): void
    {
        $user = User::factory()->create();

        $low = TransactionAlias::factory()->for($user)->create([
            'priority' => 50,
            'match_pattern' => 'low-'.uniqid(),
        ]);
        $high = TransactionAlias::factory()->for($user)->create([
            'priority' => 1,
            'match_pattern' => 'high-'.uniqid(),
        ]);
        TransactionAlias::factory()->for($user)->inactive()->create([
            'priority' => 0,
            'match_pattern' => 'inactive-'.uniqid(),
        ]);

        $ids = TransactionAlias::query()->forResolution($user)->pluck('id')->all();

        $this->assertSame([$high->id, $low->id], $ids);
    }

    public function test_unique_constraint_per_user_type_and_pattern(): void
    {
        $user = User::factory()->create();

        TransactionAlias::factory()->for($user)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'SAME',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TransactionAlias::factory()->for($user)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'SAME',
        ]);
    }

    public function test_policy_allows_owner_roles_including_visitor(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $visitor = User::factory()->visitor()->create();
        $alias = TransactionAlias::factory()->for($admin)->create();
        $visitorAlias = TransactionAlias::factory()->for($visitor)->create();

        $this->assertTrue($admin->can('view', $alias));
        $this->assertTrue($admin->can('update', $alias));
        $this->assertTrue($admin->can('create', TransactionAlias::class));
        $this->assertFalse($otherAdmin->can('view', $alias));
        $this->assertTrue($visitor->can('create', TransactionAlias::class));
        $this->assertTrue($visitor->can('view', $visitorAlias));
        $this->assertFalse($visitor->can('view', $alias));
    }

    public function test_policy_respects_aliases_manage_ability_override(): void
    {
        config([
            'aura.abilities' => [
                'aliases.manage' => [UserRole::Admin->value],
            ],
        ]);

        $subadmin = User::factory()->subadmin()->create();

        $this->assertFalse($subadmin->can('viewAny', TransactionAlias::class));
    }
}
