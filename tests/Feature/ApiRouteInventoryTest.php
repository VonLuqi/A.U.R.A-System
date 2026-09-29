<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * §5.1 — canonical API route inventory (names + auth for Etapa D).
 */
class ApiRouteInventoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function inventoryProvider(): array
    {
        return [
            ['GET', 'api/csrf-cookie', 'api.csrf-cookie'],
            ['POST', 'api/login', 'api.login'],
            ['POST', 'api/logout', 'api.logout'],
            ['GET', 'api/user', 'api.user'],
            ['POST', 'api/statements/upload', 'api.statements.upload'],
            ['GET', 'api/statements', 'api.statements.index'],
            ['GET', 'api/statements/{statementImport}', 'api.statements.show'],
            ['GET', 'api/transactions', 'api.transactions.index'],
            ['GET', 'api/transactions/{transaction}', 'api.transactions.show'],
            ['POST', 'api/transactions', 'api.transactions.store'],
            ['PATCH', 'api/transactions/{transaction}', 'api.transactions.update'],
            ['DELETE', 'api/transactions/{transaction}', 'api.transactions.destroy'],
            ['GET', 'api/aliases', 'api.aliases.index'],
            ['POST', 'api/aliases', 'api.aliases.store'],
            ['POST', 'api/aliases/preview', 'api.aliases.preview'],
            ['GET', 'api/aliases/{alias}', 'api.aliases.show'],
            ['PATCH', 'api/aliases/{alias}', 'api.aliases.update'],
            ['DELETE', 'api/aliases/{alias}', 'api.aliases.destroy'],
            ['POST', 'api/transactions/{transaction}/remember-alias', 'api.transactions.remember-alias'],
            ['GET', 'api/analytics/dashboard', 'api.analytics.dashboard'],
            ['GET', 'api/categories', 'api.categories.index'],
            ['POST', 'api/categories', 'api.categories.store'],
            ['GET', 'api/goals', 'api.goals.index'],
            ['POST', 'api/goals', 'api.goals.store'],
            ['GET', 'api/goals/{goal}', 'api.goals.show'],
            ['PATCH', 'api/goals/{goal}', 'api.goals.update'],
            ['DELETE', 'api/goals/{goal}', 'api.goals.destroy'],
            ['POST', 'api/goals/{goal}/recalculate', 'api.goals.recalculate'],
            ['GET', 'api/users', 'api.users.index'],
            ['POST', 'api/users', 'api.users.store'],
            ['GET', 'api/users/{user}', 'api.users.show'],
            ['PATCH', 'api/users/{user}', 'api.users.update'],
            ['DELETE', 'api/users/{user}', 'api.users.destroy'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('inventoryProvider')]
    public function test_inventory_route_is_registered(string $method, string $uri, string $name): void
    {
        $this->assertTrue(Route::has($name), "Missing named route [{$name}]");

        $route = Route::getRoutes()->getByName($name);
        $this->assertNotNull($route);
        $this->assertSame($method, $route->methods()[0]);
        $this->assertSame($uri, $route->uri());
    }

    public function test_protected_inventory_routes_require_authentication(): void
    {
        $this->getJson('/api/statements')->assertUnauthorized();
        $this->getJson('/api/statements/1')->assertUnauthorized();
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->postJson('/api/transactions')->assertUnauthorized();
        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
        $this->getJson('/api/categories')->assertUnauthorized();
        $this->getJson('/api/goals')->assertUnauthorized();
        $this->postJson('/api/statements/upload')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_authenticated_inventory_routes_are_implemented(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/statements')->assertOk();
        $this->actingAs($user)->getJson('/api/statements/1')->assertNotFound();
        $this->actingAs($user)->getJson('/api/transactions')->assertOk();
        $this->actingAs($user)->getJson('/api/analytics/dashboard')->assertOk();
        $this->actingAs($user)->getJson('/api/categories')->assertOk();
        $this->actingAs($user)->getJson('/api/goals')->assertOk();
    }
}
