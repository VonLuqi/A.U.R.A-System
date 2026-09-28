<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StatementImport>
 */
class StatementImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = fake()->dateTimeBetween('-6 months', 'now')->format('Y-m');
        $filename = "nubank-{$period}.csv";

        $rowsImported = fake()->numberBetween(50, 200);
        $rowsSkipped = fake()->numberBetween(0, 20);
        $rowsTotal = $rowsImported + $rowsSkipped;

        return [
            'user_id' => User::factory(),
            'original_filename' => $filename,
            'stored_path' => "statements/fake/{$filename}",
            'format' => 'csv',
            'source' => 'nubank',
            'status' => 'completed',
            'rows_total' => $rowsTotal,
            'rows_imported' => $rowsImported,
            'rows_skipped' => $rowsSkipped,
            'checksum' => fake()->sha256(),
            'error_message' => null,
            'purged_at' => null,
        ];
    }

    public function purged(): static
    {
        return $this->state(fn () => [
            'purged_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'parse failed',
        ]);
    }
}
