<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo logins for all RBAC roles (PLAN_EXPANSAO §9.2).
 *
 * Only runs in local / development / testing — never production.
 * Credentials overridable via DEMO_*_EMAIL / DEMO_*_PASSWORD env vars.
 */
class DemoRoleUsersSeeder extends Seeder
{
    /**
     * @var list<array{envEmail: string, envPassword: string, defaultEmail: string, defaultPassword: string, name: string, role: UserRole}>
     */
    private const USERS = [
        [
            'envEmail' => 'DEMO_SUBADMIN_EMAIL',
            'envPassword' => 'DEMO_SUBADMIN_PASSWORD',
            'defaultEmail' => 'subadmin@aura.local',
            'defaultPassword' => 'ChangeMeNow!123',
            'name' => 'Subadmin Demo',
            'role' => UserRole::Subadmin,
        ],
        [
            'envEmail' => 'DEMO_VISITOR_EMAIL',
            'envPassword' => 'DEMO_VISITOR_PASSWORD',
            'defaultEmail' => 'visitor@aura.local',
            'defaultPassword' => 'ChangeMeNow!123',
            'name' => 'Visitante Demo',
            'role' => UserRole::Visitor,
        ],
        [
            'envEmail' => 'DEMO_TEST_EMAIL',
            'envPassword' => 'DEMO_TEST_PASSWORD',
            'defaultEmail' => 'test@aura.local',
            'defaultPassword' => 'ChangeMeNow!123',
            'name' => 'Teste Demo',
            'role' => UserRole::Test,
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            return;
        }

        foreach (self::USERS as $spec) {
            User::query()->updateOrCreate(
                ['email' => env($spec['envEmail'], $spec['defaultEmail'])],
                [
                    'name' => $spec['name'],
                    'password' => env($spec['envPassword'], $spec['defaultPassword']),
                    'email_verified_at' => now(),
                    'role' => $spec['role'],
                    'is_active' => true,
                    'uploads_used' => 0,
                    'manual_transactions_used' => 0,
                    'quota_period_starts_at' => now()->startOfMonth(),
                ]
            );
        }
    }
}
