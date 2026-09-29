<?php

namespace Database\Factories;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Goal>
 */
class GoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $target = fake()->randomFloat(2, 500, 50000);
        $current = fake()->randomFloat(2, 0, $target * 0.8);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Reserva de emergência',
                'Viagem',
                'Quitar cartão',
                'Entrada do imóvel',
                'Fundo de estudos',
            ]),
            'kind' => fake()->randomElement(GoalKind::cases()),
            'target_amount' => number_format($target, 2, '.', ''),
            'current_amount' => number_format($current, 2, '.', ''),
            'currency' => 'BRL',
            'deadline_on' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+2 years')?->format('Y-m-d'),
            'category_id' => null,
            'linked_description_pattern' => null,
            'status' => GoalStatus::Active,
            'metadata' => null,
        ];
    }

    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => GoalKind::Savings,
        ]);
    }

    public function debtPayoff(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => GoalKind::DebtPayoff,
        ]);
    }

    public function completed(): static
    {
        return $this->afterMaking(function (\App\Models\Goal $goal): void {
            $goal->status = GoalStatus::Completed;
            $goal->current_amount = $goal->target_amount;
        });
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GoalStatus::Paused,
        ]);
    }

    public function linked(?Category $category = null, ?string $pattern = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id ?? Category::factory(),
            'linked_description_pattern' => $pattern ?? 'aporte%',
        ]);
    }
}
