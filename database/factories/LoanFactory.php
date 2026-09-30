<?php

namespace Database\Factories;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Loan>
 */
class LoanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 5000);
        $lentOn = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'user_id' => User::factory(),
            'debtor_id' => null,
            'credit_card_id' => null,
            'debtor_name' => fake()->name(),
            'kind' => LoanKind::Cash,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'BRL',
            'lent_on' => $lentOn->format('Y-m-d'),
            'due_on' => (clone $lentOn)->modify('+'.fake()->numberBetween(7, 60).' days')->format('Y-m-d'),
            'status' => LoanStatus::Open,
            'paid_amount' => '0.00',
            'paid_at' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Loan $loan): void {
            if ($loan->debtor_id !== null) {
                return;
            }

            $debtor = \App\Models\Debtor::query()->firstOrCreate(
                [
                    'user_id' => $loan->user_id,
                    'name' => $loan->debtor_name,
                ],
                ['notes' => null],
            );

            $loan->forceFill(['debtor_id' => $debtor->id])->save();
        });
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Paid,
            'paid_at' => now(),
        ])->afterMaking(function (Loan $loan): void {
            $loan->paid_amount = $loan->amount;
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LoanStatus::Open,
            'lent_on' => now()->subMonths(2)->toDateString(),
            'due_on' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
            'paid_amount' => '0.00',
            'paid_at' => null,
        ]);
    }

    /**
     * Empréstimo no limite do cartão. Passar `$card` para reutilizar um cartão
     * (garante o mesmo `user_id`); sem argumento, cria um cartão no afterCreating.
     */
    public function cardLimit(?CreditCard $card = null): static
    {
        if ($card !== null) {
            return $this->state(fn (array $attributes) => [
                'kind' => LoanKind::CardLimit,
                'credit_card_id' => $card->id,
                'user_id' => $card->user_id,
            ]);
        }

        return $this->state(fn (array $attributes) => [
            'kind' => LoanKind::CardLimit,
        ])->afterCreating(function (Loan $loan): void {
            if ($loan->credit_card_id !== null) {
                return;
            }

            $card = CreditCard::factory()->create([
                'user_id' => $loan->user_id,
            ]);

            $loan->forceFill([
                'credit_card_id' => $card->id,
                'kind' => LoanKind::CardLimit,
            ])->save();
        });
    }
}
