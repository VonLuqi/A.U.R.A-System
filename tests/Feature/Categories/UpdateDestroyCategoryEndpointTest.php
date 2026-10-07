<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDestroyCategoryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_requires_authentication(): void
    {
        $category = Category::factory()->create();

        $this->patchJson("/api/categories/{$category->id}", [
            'name' => 'Novo nome',
        ])->assertUnauthorized();
    }

    public function test_updates_user_category_name_type_and_color(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Antiga',
            'slug' => 'antiga',
            'type' => 'expense',
            'color' => '#DCCFFF',
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'Serviços',
                'type' => 'income',
                'color' => '#A8E6C3',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Serviços')
            ->assertJsonPath('data.slug', 'servicos')
            ->assertJsonPath('data.type', 'income')
            ->assertJsonPath('data.color', '#A8E6C3')
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Serviços',
            'slug' => 'servicos',
            'type' => 'income',
        ]);
    }

    public function test_system_category_rejects_type_change(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Alimentação',
            'slug' => 'alimentacao',
            'type' => 'expense',
            'is_system' => true,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'Alimentação',
                'type' => 'income',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);

        $this->assertSame('expense', $category->fresh()->type);
    }

    public function test_system_category_allows_name_and_color_update(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Alimentação',
            'slug' => 'alimentacao',
            'type' => 'expense',
            'color' => '#DCCFFF',
            'is_system' => true,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'Comida',
                'color' => '#FFAABB',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Comida')
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.is_system', true);
    }

    public function test_destroy_user_category_nulls_transaction_links(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_system' => false]);
        $import = StatementImport::factory()->for($user)->create();
        $transaction = Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Categoria excluída.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($transaction->fresh()->category_id);
    }

    public function test_destroy_system_category_returns_422(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_system' => true]);

        $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_index_includes_is_system_and_usage_counts(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['is_system' => false, 'name' => 'Alpha']);
        $import = StatementImport::factory()->for($user)->create();
        Transaction::factory()->for($import, 'statementImport')->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.is_system', false)
            ->assertJsonPath('data.0.transactions_count', 1)
            ->assertJsonPath('data.0.usage_count', 1);
    }
}
