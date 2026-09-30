<?php

namespace Database\Factories;

use App\Enums\InstallmentPlanStatus;
use App\Models\InstallmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentPlan>
 */
class InstallmentPlanFactory extends Factory
{
    protected $model = InstallmentPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'credit_card_id' => null,
            'debtor_id' => null,
            'title' => fake()->words(3, true),
            'total_count' => 10,
            'installment_amount' => number_format(fake()->randomFloat(2, 20, 200), 2, '.', ''),
            'currency' => 'BRL',
            'status' => InstallmentPlanStatus::Open,
            'notes' => null,
        ];
    }
}
