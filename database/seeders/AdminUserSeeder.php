<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the single admin user (idempotent).
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@aura.local')],
            [
                'name' => 'Admin',
                'password' => env('ADMIN_PASSWORD', 'ChangeMeNow!123'),
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
                'is_active' => true,
            ]
        );
    }
}
