<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.6 — GET /api/categories (ordered list, no pagination).
 */
class IndexCategoriesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
    }

    public function test_lists_categories_ordered_by_name_without_pagination(): void
    {
        $user = User::factory()->create();

        Category::factory()->create([
            'name' => 'Transporte',
            'slug' => 'transporte',
            'type' => 'expense',
            'color' => '#A8E6C3',
        ]);
        Category::factory()->create([
            'name' => 'Alimentação',
            'slug' => 'alimentacao',
            'type' => 'expense',
            'color' => '#DCCFFF',
        ]);
        Category::factory()->create([
            'name' => 'Receitas',
            'slug' => 'receitas',
            'type' => 'income',
            'color' => '#A8E6C3',
        ]);

        $response = $this->actingAs($user)->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'type', 'color', 'is_system'],
                ],
            ])
            ->assertJsonMissingPath('meta')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Alimentação')
            ->assertJsonPath('data.1.name', 'Receitas')
            ->assertJsonPath('data.2.name', 'Transporte');

        $receitas = collect($response->json('data'))->firstWhere('slug', 'receitas');
        $this->assertSame('income', $receitas['type']);
        $this->assertSame('#A8E6C3', $receitas['color']);
    }

    public function test_returns_empty_data_when_no_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
