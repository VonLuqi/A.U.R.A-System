<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_login_succeeds_with_valid_credentials(): void
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
            ->assertJsonPath('user.email', 'admin@aura.local')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure([
                'user' => [
                    'limits',
                    'usage',
                    'abilities',
                ],
            ])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@aura.local',
            'password' => 'ChangeMeNow!123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@aura.local',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Credenciais inválidas.']);

        $this->assertGuest();
    }

    public function test_login_when_already_authenticated_returns_current_user(): void
    {
        $user = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@aura.local',
        ]);

        // No credentials required: guest middleware short-circuits with current user.
        $response = $this->actingAs($user)->postJson('/api/login');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Admin')
            ->assertJsonPath('user.email', 'admin@aura.local')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure([
                'user' => [
                    'limits',
                    'usage',
                    'abilities',
                ],
            ]);
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_too_many_failures(): void
    {
        User::factory()->create([
            'email' => 'admin@aura.local',
            'password' => 'ChangeMeNow!123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'admin@aura.local',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $response = $this->postJson('/api/login', [
            'email' => 'admin@aura.local',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }
}
