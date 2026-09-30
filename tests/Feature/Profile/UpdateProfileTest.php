<?php

namespace Tests\Feature\Profile;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PLAN_PERFIL_BRANDING §6.1 — PATCH /api/profile (self-service).
 */
class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_authenticated_user_updates_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Nome Antigo',
            'email' => 'me@aura.local',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'name' => 'Nome Novo',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nome Novo')
            ->assertJsonPath('user.email', 'me@aura.local')
            ->assertJsonPath('user.avatar_url', null)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.avatar_path');

        $this->assertSame('Nome Novo', $user->fresh()->name);
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldSecret1'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'password' => 'NewSecret1',
                'password_confirmation' => 'NewSecret1',
                'current_password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('OldSecret1', $user->fresh()->password));

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'password' => 'NewSecret1',
                'password_confirmation' => 'NewSecret1',
                'current_password' => 'OldSecret1',
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertTrue(Hash::check('NewSecret1', $user->fresh()->password));
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/profile', [
            'name' => 'Hacker',
        ])->assertUnauthorized();
    }

    public function test_email_and_role_in_payload_are_ignored(): void
    {
        $user = User::factory()->visitor()->create([
            'name' => 'Visitante',
            'email' => 'visitor@aura.local',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/profile', [
                'name' => 'Visitante Atualizado',
                'email' => 'stolen@aura.local',
                'role' => UserRole::Admin->value,
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Visitante Atualizado')
            ->assertJsonPath('user.email', 'visitor@aura.local')
            ->assertJsonPath('user.role', 'visitor');

        $fresh = $user->fresh();
        $this->assertSame('visitor@aura.local', $fresh->email);
        $this->assertSame(UserRole::Visitor, $fresh->role);
    }
}
