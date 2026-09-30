<?php

namespace Database\Factories;

use App\Enums\InstallmentItemStatus;
use App\Models\InstallmentItem;
use App\Models\InstallmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentItem>
 */
class InstallmentItemFactory extends Factory
{
    protected $model = InstallmentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'installment_plan_id' => InstallmentPlan::factory(),
            'number' => 1,
            'amount' => number_format(fake()->randomFloat(2, 20, 200), 2, '.', ''),
            'due_on' => now()->toDateString(),
            'status' => InstallmentItemStatus::Open,
            'paid_at' => null,
            'transaction_id' => null,
            'loan_id' => null,
        ];
    }
}
