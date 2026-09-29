<?php

namespace Database\Factories;

use App\Enums\TransactionSourceKind;
use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @var list<string>
     */
    private const DESCRIPTIONS = [
        'Pagamento recebido',
        'Transferência recebida pelo Pix',
        'Supermercado Extra',
        'Uber *Trip',
        'iFood *Pedido',
        'Farmácia Droga Raia',
        'Netflix.Com',
        'Spotify Ab',
        'Amazon Marketplace',
        'Posto Shell',
        'Condomínio',
        'Energia Elétrica',
        'Salário',
        'Rendimento Conta',
        'Padaria',
    ];

    /**
     * @return static
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Transaction $transaction): void {
            if ($transaction->user_id !== null) {
                return;
            }

            if ($transaction->statement_import_id !== null) {
                $transaction->user_id = StatementImport::query()
                    ->whereKey($transaction->statement_import_id)
                    ->value('user_id');

                return;
            }

            if ($transaction->relationLoaded('statementImport') && $transaction->statementImport !== null) {
                $transaction->user_id = $transaction->statementImport->user_id;
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->boolean(25) ? 'credit' : 'debit';
        $amount = $type === 'credit'
            ? fake()->randomFloat(2, 50, 8000)
            : fake()->randomFloat(2, 5, 600);

        $baseDescription = fake()->randomElement(self::DESCRIPTIONS);
        // Unique suffix keeps unique_hash collision-free at high volume (seed only).
        $description = $baseDescription.' #'.fake()->unique()->numerify('####');

        $occurredOn = fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d');
        $externalId = fake()->boolean(30) ? fake()->uuid() : null;
        $source = 'nubank';

        $categoryId = null;
        if (! fake()->boolean(15) && Category::query()->exists()) {
            $categoryId = Category::query()->inRandomOrder()->value('id');
        }

        return [
            'statement_import_id' => StatementImport::factory(),
            'source_kind' => TransactionSourceKind::Import,
            'category_id' => $categoryId,
            'external_id' => $externalId,
            'occurred_on' => $occurredOn,
            'description' => $description,
            'amount' => number_format($amount, 2, '.', ''),
            'type' => $type,
            'unique_hash' => Transaction::makeUniqueHash(
                $occurredOn,
                $amount,
                $type,
                $description,
                $externalId,
                $source,
            ),
            'raw_payload' => [
                'seed' => true,
                'description' => $description,
            ],
        ];
    }

    public function manual(): static
    {
        return $this->state(function (array $attributes) {
            $userId = $attributes['user_id'] ?? null;

            return [
                'user_id' => $userId ?? User::factory(),
                'statement_import_id' => null,
                'source_kind' => TransactionSourceKind::Manual,
                'raw_payload' => [
                    'origin' => 'manual',
                    'seed' => true,
                ],
            ];
        })->afterMaking(function (Transaction $transaction): void {
            $userId = (int) ($transaction->user_id ?? 0);
            $transaction->unique_hash = \App\Support\TransactionHasher::make(
                $transaction->occurred_on instanceof \DateTimeInterface
                    ? $transaction->occurred_on->format('Y-m-d')
                    : (string) $transaction->occurred_on,
                $transaction->amount,
                (string) $transaction->type,
                (string) $transaction->description,
                null,
                'manual:'.$userId,
            );
        });
    }
}