<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditCard>
 */
class CreditCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Nubank',
                'Inter',
                'C6',
                'Itaú',
                'XP',
                'Santander',
            ]).' '.fake()->unique()->bothify('????-##'),
            'limit_amount' => number_format(fake()->randomFloat(2, 1000, 25000), 2, '.', ''),
            'currency' => 'BRL',
            'closing_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
            'last_four' => fake()->optional(0.7)->numerify('####'),
            'is_active' => true,
            'is_default' => false,
            'notes' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'is_default' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
