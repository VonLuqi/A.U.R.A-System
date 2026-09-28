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
            ['GET', 'api/analytics/dashboard', 'api.analytics.dashboard'],
            ['GET', 'api/categories', 'api.categories.index'],
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
        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
        $this->getJson('/api/categories')->assertUnauthorized();
        $this->postJson('/api/statements/upload')->assertUnauthorized();
    }

    public function test_authenticated_inventory_routes_are_implemented(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/statements')->assertOk();
        $this->actingAs($user)->getJson('/api/statements/1')->assertNotFound();
        $this->actingAs($user)->getJson('/api/transactions')->assertOk();
        $this->actingAs($user)->getJson('/api/analytics/dashboard')->assertOk();
        $this->actingAs($user)->getJson('/api/categories')->assertOk();
    }
}
