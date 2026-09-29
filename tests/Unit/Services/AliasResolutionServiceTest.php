<?php

namespace Tests\Unit\Services;

use App\DTOs\AliasMatch;
use App\Enums\AliasMatchType;
use App\Models\Category;
use App\Models\TransactionAlias;
use App\Models\User;
use App\Services\AliasResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §4.1 — AliasResolutionService matching + memoization.
 */
class AliasResolutionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_first_priority_match(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        TransactionAlias::factory()->for($user)->create([
            'priority' => 20,
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'IFOOD',
            'display_name' => 'iFood baixo',
            'category_id' => null,
        ]);

        $winner = TransactionAlias::factory()->for($user)->withCategory($category)->create([
            'priority' => 1,
            'match_type' => AliasMatchType::StartsWith,
            'match_pattern' => 'Pg *IFOOD',
            'display_name' => 'iFood',
        ]);

        TransactionAlias::factory()->for($user)->inactive()->create([
            'priority' => 0,
            'match_type' => AliasMatchType::Regex,
            'match_pattern' => 'IFOOD',
            'display_name' => 'inactive',
        ]);

        $service = app(AliasResolutionService::class);
        $match = $service->resolve($user, 'Pg *IFOOD* Pedido 123');

        $this->assertInstanceOf(AliasMatch::class, $match);
        $this->assertSame($winner->id, $match->aliasId);
        $this->assertSame('iFood', $match->displayName);
        $this->assertSame($category->id, $match->categoryId);
    }

    public function test_resolve_supports_exact_starts_with_and_regex(): void
    {
        $user = User::factory()->create();
        $service = app(AliasResolutionService::class);

        TransactionAlias::factory()->for($user)->exact()->create([
            'priority' => 1,
            'match_pattern' => 'Uber Trip',
            'display_name' => 'Uber',
        ]);
        $this->assertSame('Uber', $service->resolve($user, 'uber trip')?->displayName);
        $this->assertNull($service->resolve($user, 'Uber Trip extra'));

        $service->forget($user);
        TransactionAlias::query()->forUser($user)->delete();

        TransactionAlias::factory()->for($user)->startsWith()->create([
            'priority' => 1,
            'match_pattern' => 'NU PAG',
            'display_name' => 'Nubank',
        ]);
        $this->assertSame('Nubank', $service->resolve($user, 'NU PAGAMENTOS S.A.')?->displayName);

        $service->forget($user);
        TransactionAlias::query()->forUser($user)->delete();

        TransactionAlias::factory()->for($user)->regex()->create([
            'priority' => 1,
            'match_pattern' => 'uber|99\\s*app',
            'display_name' => 'Transporte',
        ]);
        $this->assertSame('Transporte', $service->resolve($user, '99 app corrida')?->displayName);
    }

    public function test_aliases_are_memoized_until_data_changes(): void
    {
        $user = User::factory()->create();
        TransactionAlias::factory()->for($user)->create([
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => 'ALPHA',
            'display_name' => 'A',
            'priority' => 1,
        ]);

        $service = app(AliasResolutionService::class);
        $this->assertSame('A', $service->resolve($user, 'ALPHA store')?->displayName);
        // Same version → memoized list reused (still A).
        $this->assertSame('A', $service->resolve($user, 'ALPHA store')?->displayName);

        // Inserting a higher-priority rule changes the version fingerprint → auto-refresh.
        TransactionAlias::factory()->for($user)->create([
            'match_type' => AliasMatchType::StartsWith,
            'match_pattern' => 'ALPHA',
            'display_name' => 'B',
            'priority' => 0,
        ]);
        $this->assertSame('B', $service->resolve($user, 'ALPHA store')?->displayName);

        $service->forget($user);
        $this->assertSame('B', $service->resolve($user, 'ALPHA store')?->displayName);
    }

    public function test_does_not_leak_aliases_across_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        TransactionAlias::factory()->for($a)->create([
            'match_pattern' => 'SHARED',
            'display_name' => 'Owner A',
            'priority' => 1,
        ]);

        $service = app(AliasResolutionService::class);

        $this->assertSame('Owner A', $service->resolve($a, 'SHARED xyz')?->displayName);
        $this->assertNull($service->resolve($b, 'SHARED xyz'));
    }
}
