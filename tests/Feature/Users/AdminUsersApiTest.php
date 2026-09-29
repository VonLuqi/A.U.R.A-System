<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_visitor_cannot_list_users(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_admin_lists_users_with_filters(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->visitor()->create(['name' => 'Alice Visitor', 'email' => 'alice@aura.local']);
        User::factory()->subadmin()->create(['name' => 'Bob Sub', 'email' => 'bob@aura.local']);
        User::factory()->test()->inactive()->create(['name' => 'Inactive Test']);

        $this->actingAs($admin)
            ->getJson('/api/users?role=visitor&q=alice')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'alice@aura.local')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'name', 'email', 'role', 'is_active',
                    'limits' => ['max_uploads', 'max_manual_transactions'],
                    'usage' => ['uploads_used', 'manual_transactions_used'],
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->actingAs($admin)
            ->getJson('/api/users?is_active=0')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.role', 'test');
    }

    public function test_admin_creates_assignable_roles_but_not_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'New Visitor',
                'email' => 'new@aura.local',
                'password' => 'secret123',
                'role' => UserRole::Visitor->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new@aura.local')
            ->assertJsonPath('data.role', 'visitor')
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('secret123', User::query()->where('email', 'new@aura.local')->first()->password));

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Hacker',
                'email' => 'hacker@aura.local',
                'password' => 'secret123',
                'role' => UserRole::Admin->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_admin_updates_role_and_resets_usage(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->visitor()->create([
            'uploads_used' => 4,
            'manual_transactions_used' => 9,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->actingAs($admin)
            ->patchJson('/api/users/'.$target->id, [
                'role' => UserRole::Subadmin->value,
                'reset_usage' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'subadmin')
            ->assertJsonPath('data.usage.uploads_used', 0)
            ->assertJsonPath('data.usage.manual_transactions_used', 0);
    }

    public function test_admin_cannot_modify_another_admin_via_api(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson('/api/users/'.$otherAdmin->id, [
                'name' => 'Nope',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    public function test_delete_soft_blocks_non_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->visitor()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->deleteJson('/api/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('message', 'Usuário desativado (soft-block).');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'is_active' => 0,
        ]);
    }

    public function test_cannot_delete_self_or_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson('/api/users/'.$admin->id)
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson('/api/users/'.$otherAdmin->id)
            ->assertForbidden();
    }

    public function test_show_user_returns_resource(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->test()->create();

        $this->actingAs($admin)
            ->getJson('/api/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.role', 'test');
    }
}
