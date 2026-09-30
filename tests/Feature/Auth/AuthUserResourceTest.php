<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §8.1 — AuthUser payload (role, limits, usage, abilities).
 */
class AuthUserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);

        // Contrato AuthUser: assert abilities H só quando flags off (default de produção).
        config([
            'aura.features.credit_cards' => false,
            'aura.features.loans' => false,
            'aura.features.notifications' => false,
        ]);
    }

    public function test_api_user_returns_role_limits_usage_and_abilities_for_admin(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Aura',
            'email' => 'admin@aura.local',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.name', 'Admin Aura')
            ->assertJsonPath('user.email', 'admin@aura.local')
            ->assertJsonPath('user.avatar_url', null)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.is_active', true)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'avatar_url',
                    'role',
                    'is_active',
                    'limits' => [
                        'max_uploads',
                        'max_manual_transactions',
                        'max_date_range_days',
                        'max_goals',
                        'max_aliases',
                        'max_credit_cards',
                        'max_loans',
                    ],
                    'usage' => [
                        'uploads_used',
                        'manual_transactions_used',
                        'quota_period_starts_at',
                        'uploads_remaining',
                        'manual_transactions_remaining',
                        'goals_used',
                        'goals_remaining',
                        'aliases_used',
                        'aliases_remaining',
                        'credit_cards_used',
                        'credit_cards_remaining',
                        'loans_used',
                        'loans_remaining',
                    ],
                    'abilities',
                    'features' => [
                        'manual_transactions',
                        'goals',
                        'aliases',
                        'credit_card_upload',
                        'admin_users',
                        'credit_cards',
                        'loans',
                        'notifications',
                    ],
                ],
            ]);

        $abilities = $response->json('user.abilities');
        $this->assertIsArray($abilities);
        $this->assertContains('users.manage', $abilities);
        $this->assertContains('statements.upload', $abilities);
        $this->assertContains('goals.manage', $abilities);
        $this->assertContains('aliases.manage', $abilities);
        $this->assertContains('transactions.manage', $abilities);
        // Etapa H flags default off → abilities omitted until enabled.
        $this->assertNotContains('credit_cards.manage', $abilities);
        $this->assertNotContains('loans.manage', $abilities);
        $this->assertNotContains('notifications.read', $abilities);

        $this->assertTrue($response->json('user.features.credit_card_upload'));
        $this->assertFalse($response->json('user.features.credit_cards'));
        $this->assertFalse($response->json('user.features.loans'));
        $this->assertFalse($response->json('user.features.notifications'));
        $this->assertNull($response->json('user.usage.uploads_remaining'));
        $this->assertSame(0, $response->json('user.limits.max_uploads'));
        $this->assertSame(0, $response->json('user.limits.max_credit_cards'));
        $this->assertSame(0, $response->json('user.limits.max_loans'));
    }

    public function test_api_user_abilities_exclude_users_manage_for_visitor(): void
    {
        $visitor = User::factory()->visitor()->create([
            'uploads_used' => 1,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $response = $this->actingAs($visitor)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.role', 'visitor')
            ->assertJsonPath('user.limits.max_uploads', 5)
            ->assertJsonPath('user.usage.uploads_used', 1)
            ->assertJsonPath('user.usage.uploads_remaining', 4);

        $abilities = $response->json('user.abilities');
        $this->assertNotContains('users.manage', $abilities);
        $this->assertContains('statements.upload', $abilities);
        $this->assertContains('goals.manage', $abilities);
        $this->assertContains('aliases.manage', $abilities);
    }

    public function test_api_user_avatar_url_is_public_storage_url_when_path_set(): void
    {
        $user = User::factory()->withAvatar('avatars/42/fake.webp')->create();

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk();

        $avatarUrl = $response->json('user.avatar_url');
        $this->assertIsString($avatarUrl);
        $this->assertStringContainsString('/storage/avatars/42/fake.webp', $avatarUrl);
        $this->assertStringNotContainsString('avatar_path', (string) json_encode($response->json('user')));
    }

    public function test_login_payload_includes_auth_user_shape(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@aura.local',
            'password' => 'ChangeMeNow!123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@aura.local',
            'password' => 'ChangeMeNow!123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure([
                'user' => [
                    'avatar_url',
                    'limits',
                    'usage',
                    'abilities',
                ],
            ])
            ->assertJsonPath('user.avatar_url', null)
            ->assertJsonMissingPath('user.password');
    }
}
