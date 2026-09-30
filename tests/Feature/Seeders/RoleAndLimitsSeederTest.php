<?php

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Models\RoleLimit;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndLimitsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_limits_seeder_creates_defaults_for_all_roles(): void
    {
        $this->seed(RoleLimitsSeeder::class);

        $this->assertDatabaseCount('role_limits', 4);

        $visitor = RoleLimit::query()->where('role', UserRole::Visitor)->first();
        $this->assertNotNull($visitor);
        $this->assertSame(5, $visitor->max_uploads);
        $this->assertSame(20, $visitor->max_manual_transactions);
        $this->assertSame(90, $visitor->max_date_range_days);
        $this->assertSame(3, $visitor->max_goals);
        $this->assertSame(3, $visitor->max_credit_cards);
        $this->assertSame(5, $visitor->max_loans);

        $admin = RoleLimit::query()->where('role', UserRole::Admin)->first();
        $this->assertNotNull($admin);
        $this->assertTrue(RoleLimit::isUnlimited($admin->max_uploads));
        $this->assertTrue(RoleLimit::isUnlimited($admin->max_credit_cards));
        $this->assertTrue(RoleLimit::isUnlimited($admin->max_loans));
    }

    public function test_role_limits_seeder_is_idempotent(): void
    {
        $this->seed(RoleLimitsSeeder::class);
        $this->seed(RoleLimitsSeeder::class);

        $this->assertDatabaseCount('role_limits', 4);
    }

    public function test_admin_user_seeder_assigns_admin_role(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()
            ->where('email', env('ADMIN_EMAIL', 'admin@aura.local'))
            ->first();

        $this->assertNotNull($admin);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_demo_role_users_seeder_creates_assignable_roles(): void
    {
        $this->seed(RoleLimitsSeeder::class);
        $this->seed(AdminUserSeeder::class);
        $this->seed(\Database\Seeders\DemoRoleUsersSeeder::class);

        $subadmin = User::query()->where('email', 'subadmin@aura.local')->first();
        $visitor = User::query()->where('email', 'visitor@aura.local')->first();
        $test = User::query()->where('email', 'test@aura.local')->first();

        $this->assertNotNull($subadmin);
        $this->assertSame(UserRole::Subadmin, $subadmin->role);
        $this->assertNotNull($visitor);
        $this->assertSame(UserRole::Visitor, $visitor->role);
        $this->assertNotNull($test);
        $this->assertSame(UserRole::Test, $test->role);
    }

    public function test_user_factory_role_states(): void
    {
        $this->assertSame(UserRole::Admin, User::factory()->admin()->make()->role);
        $this->assertSame(UserRole::Subadmin, User::factory()->subadmin()->make()->role);
        $this->assertSame(UserRole::Visitor, User::factory()->visitor()->make()->role);
        $this->assertSame(UserRole::Test, User::factory()->test()->make()->role);
        $this->assertFalse(User::factory()->inactive()->make()->is_active);
    }
}
