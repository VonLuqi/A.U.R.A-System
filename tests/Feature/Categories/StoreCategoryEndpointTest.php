<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreCategoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/categories', ['name' => 'Mercado'])->assertUnauthorized();
    }

    public function test_creates_category_and_lists_it(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/categories', [
            'name' => 'Mercado',
            'type' => 'expense',
            'color' => '#DCCFFF',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mercado')
            ->assertJsonPath('data.slug', 'mercado')
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.color', '#DCCFFF');

        $this->assertDatabaseHas('categories', [
            'name' => 'Mercado',
            'slug' => 'mercado',
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Mercado']);
    }

    public function test_unique_slug_gets_suffix(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Outros', 'slug' => 'outros']);

        $response = $this->actingAs($user)->postJson('/api/categories', [
            'name' => 'Outros',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'outros-1');
    }
}
