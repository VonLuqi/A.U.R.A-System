<?php

namespace Tests\Feature\Transactions;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBulkApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_update_with_apply_category_to_matching_updates_same_description(): void
    {
        $user = User::factory()->admin()->create();
        $pet = Category::factory()->create(['name' => 'Pet']);
        $other = Category::factory()->create(['name' => 'Outros']);

        $source = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-20',
            'description' => 'Petshop Centro',
            'type' => 'debit',
            'amount' => '50.00',
            'category_id' => $other->id,
            'raw_payload' => ['original_description' => 'Petshop Centro'],
        ]);
        $sibling = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-10',
            'description' => 'Petshop Centro',
            'type' => 'debit',
            'amount' => '30.00',
            'category_id' => $other->id,
            'raw_payload' => ['original_description' => 'Petshop Centro'],
        ]);
        $unrelated = Transaction::factory()->manual()->for($user)->create([
            'occurred_on' => '2026-09-05',
            'description' => 'Mercado Extra',
            'type' => 'debit',
            'amount' => '20.00',
            'category_id' => $other->id,
            'raw_payload' => ['original_description' => 'Mercado Extra'],
        ]);

        $response = $this->actingAs($user)->patchJson("/api/transactions/{$source->id}", [
            'category_id' => $pet->id,
            'apply_category_to_matching' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.category.id', $pet->id)
            ->assertJsonPath('retroactive.updated', 1);

        $this->assertSame($pet->id, $source->fresh()->category_id);
        $this->assertSame($pet->id, $sibling->fresh()->category_id);
        $this->assertSame($other->id, $unrelated->fresh()->category_id);
    }

    public function test_update_without_flag_does_not_bulk_apply(): void
    {
        $user = User::factory()->admin()->create();
        $pet = Category::factory()->create(['name' => 'Pet']);
        $other = Category::factory()->create(['name' => 'Outros']);

        $source = Transaction::factory()->manual()->for($user)->create([
            'description' => 'Petshop Centro',
            'category_id' => $other->id,
            'raw_payload' => ['original_description' => 'Petshop Centro'],
        ]);
        $sibling = Transaction::factory()->manual()->for($user)->create([
            'description' => 'Petshop Centro',
            'category_id' => $other->id,
            'raw_payload' => ['original_description' => 'Petshop Centro'],
        ]);

        $this->actingAs($user)->patchJson("/api/transactions/{$source->id}", [
            'category_id' => $pet->id,
        ])->assertOk()->assertJsonMissingPath('retroactive');

        $this->assertSame($other->id, $sibling->fresh()->category_id);
    }
}
