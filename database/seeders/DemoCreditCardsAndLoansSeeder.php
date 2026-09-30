<?php

namespace Database\Seeders;

use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo cartões + cobranças (PLAN_CARTOES_EMPRESTIMOS §1.6).
 *
 * Only runs in local / development / testing — never production.
 */
class DemoCreditCardsAndLoansSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            return;
        }

        $this->call([
            AdminUserSeeder::class,
            DemoRoleUsersSeeder::class,
        ]);

        $admin = User::query()
            ->where('email', env('ADMIN_EMAIL', 'admin@aura.local'))
            ->firstOrFail();

        if (CreditCard::query()->where('user_id', $admin->id)->exists()) {
            return;
        }

        $nubank = CreditCard::factory()->default()->create([
            'user_id' => $admin->id,
            'name' => 'Nubank Roxinho',
            'closing_day' => 5,
            'due_day' => 12,
            'last_four' => '4242',
            'limit_amount' => '8000.00',
        ]);

        CreditCard::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Inter Black',
            'closing_day' => 20,
            'due_day' => 27,
            'last_four' => '1890',
            'limit_amount' => '12000.00',
        ]);

        CreditCard::factory()->inactive()->create([
            'user_id' => $admin->id,
            'name' => 'Cartão Antigo (inativo)',
            'closing_day' => 1,
            'due_day' => 8,
        ]);

        Loan::factory()->create([
            'user_id' => $admin->id,
            'debtor_name' => 'João Silva',
            'amount' => '350.00',
            'lent_on' => now()->subWeeks(2)->toDateString(),
            'due_on' => now()->addDays(5)->toDateString(),
        ]);

        Loan::factory()->cardLimit($nubank)->create([
            'user_id' => $admin->id,
            'debtor_name' => 'Maria Souza',
            'amount' => '189.90',
            'lent_on' => now()->subMonth()->toDateString(),
            'due_on' => now()->addDays(10)->toDateString(),
        ]);

        Loan::factory()->overdue()->create([
            'user_id' => $admin->id,
            'debtor_name' => 'Pedro Costa',
            'amount' => '500.00',
        ]);

        Loan::factory()->paid()->create([
            'user_id' => $admin->id,
            'debtor_name' => 'Ana Lima',
            'amount' => '120.00',
        ]);
    }
}
