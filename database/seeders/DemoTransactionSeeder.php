<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoTransactionSeeder extends Seeder
{
    /**
     * Seed demo statement imports + transactions for local UI work.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            return;
        }

        $this->call([
            AdminUserSeeder::class,
            CategorySeeder::class,
        ]);

        $admin = User::query()
            ->where('email', env('ADMIN_EMAIL', 'admin@aura.local'))
            ->firstOrFail();

        $categoriesBySlug = Category::query()->pluck('id', 'slug');

        $imports = collect([
            now()->subMonths(2)->format('Y-m'),
            now()->subMonth()->format('Y-m'),
            now()->format('Y-m'),
        ])->map(function (string $period) use ($admin) {
            $filename = "nubank-{$period}.csv";

            return StatementImport::query()->create([
                'user_id' => $admin->id,
                'original_filename' => $filename,
                'stored_path' => "statements/fake/{$filename}",
                'format' => 'csv',
                'source' => 'nubank',
                'status' => 'completed',
                'rows_total' => 0,
                'rows_imported' => 0,
                'rows_skipped' => 0,
                'checksum' => hash('sha256', $filename.'|demo'),
                'error_message' => null,
            ]);
        });

        $perImport = 100;

        foreach ($imports as $import) {
            $transactions = Transaction::factory()
                ->count($perImport)
                ->for($import)
                ->create();

            foreach ($transactions as $transaction) {
                $slug = $this->resolveCategorySlug($transaction->description, $transaction->type);
                $categoryId = $categoriesBySlug->get($slug);

                if ($categoryId !== null) {
                    $transaction->forceFill(['category_id' => $categoryId])->save();
                }
            }

            $import->forceFill([
                'rows_total' => $perImport,
                'rows_imported' => $perImport,
                'rows_skipped' => 0,
            ])->save();
        }
    }

    private function resolveCategorySlug(string $description, string $type): string
    {
        $text = mb_strtolower($description);

        return match (true) {
            str_contains($text, 'salário'),
            str_contains($text, 'salario'),
            str_contains($text, 'pagamento recebido'),
            str_contains($text, 'rendimento') => 'receitas',
            str_contains($text, 'pix') && $type === 'credit' => 'receitas',
            str_contains($text, 'supermercado'),
            str_contains($text, 'ifood'),
            str_contains($text, 'padaria') => 'alimentacao',
            str_contains($text, 'uber'),
            str_contains($text, 'posto') => 'transporte',
            str_contains($text, 'condomínio'),
            str_contains($text, 'condominio'),
            str_contains($text, 'energia') => 'moradia',
            str_contains($text, 'farmácia'),
            str_contains($text, 'farmacia') => 'saude',
            str_contains($text, 'netflix'),
            str_contains($text, 'spotify'),
            str_contains($text, 'amazon') => 'lazer',
            str_contains($text, 'transferência'),
            str_contains($text, 'transferencia') => 'transferencias',
            default => 'outros',
        };
    }
}
