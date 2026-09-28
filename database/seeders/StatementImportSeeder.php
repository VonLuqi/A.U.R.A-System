<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Imports fake de desenvolvimento são criados pelo DemoTransactionSeeder (§6),
 * junto com as transactions, para manter counters e FKs coerentes.
 * Este seeder permanece intencionalmente vazio.
 */
class StatementImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Intentionally empty — see DemoTransactionSeeder.
    }
}
