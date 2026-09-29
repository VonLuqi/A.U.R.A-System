<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleLimitsSeeder::class,
            AdminUserSeeder::class,
            DemoRoleUsersSeeder::class, // no-op outside local/development/testing
            CategorySeeder::class,
            DemoTransactionSeeder::class, // no-op outside local/development/testing
        ]);
    }
}
