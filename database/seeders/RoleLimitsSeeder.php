<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\RoleLimit;
use Illuminate\Database\Seeder;

class RoleLimitsSeeder extends Seeder
{
    /**
     * Seed default quota limits per role (idempotent).
     *
     * Source of truth: config/aura.php (`limits`). Convention: `0` = unlimited.
     */
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            $limits = config('aura.limits.'.$role->value, []);

            RoleLimit::query()->updateOrCreate(
                ['role' => $role->value],
                [
                    'max_uploads' => (int) ($limits['max_uploads'] ?? 0),
                    'max_manual_transactions' => (int) ($limits['max_manual_transactions'] ?? 0),
                    'max_date_range_days' => (int) ($limits['max_date_range_days'] ?? 0),
                    'max_goals' => (int) ($limits['max_goals'] ?? 0),
                ]
            );
        }
    }
}
