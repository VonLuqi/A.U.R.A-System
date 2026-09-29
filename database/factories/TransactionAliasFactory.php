<?php

namespace Database\Factories;

use App\Enums\AliasMatchType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionAlias>
 */
class TransactionAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $merchant = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'match_type' => AliasMatchType::Contains,
            'match_pattern' => $merchant,
            'display_name' => fake()->randomElement([
                'Mercado',
                'Transporte',
                'Streaming',
                'Farmácia',
                'Restaurante',
            ]),
            'category_id' => null,
            'priority' => fake()->numberBetween(1, 200),
            'is_active' => true,
        ];
    }

    public function exact(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_type' => AliasMatchType::Exact,
        ]);
    }

    public function contains(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_type' => AliasMatchType::Contains,
        ]);
    }

    public function startsWith(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_type' => AliasMatchType::StartsWith,
        ]);
    }

    public function regex(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_type' => AliasMatchType::Regex,
            'match_pattern' => 'uber|99app',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id ?? Category::factory(),
        ]);
    }
}
