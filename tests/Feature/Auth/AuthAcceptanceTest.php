<?php

namespace Tests\Feature\Auth;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Etapa C §1.9 — Auth acceptance criteria (end-to-end checklist).
 */
class AuthAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_unauthenticated_protected_route_returns_401_json(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->postJson('/api/logout')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->postJson('/api/statements/upload')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_admin_login_with_env_credentials_sets_session_and_returns_200(): void
    {
        $this->seed(AdminUserSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@aura.local');
        $password = env('ADMIN_PASSWORD', 'ChangeMeNow!123');

        $response = $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', $email)
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticated();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.email', $email);
    }

    public function test_invalid_login_does_not_reveal_whether_email_exists(): void
    {
        $this->seed(AdminUserSeeder::class);

        $unknown = $this->postJson('/api/login', [
            'email' => 'nobody@aura.local',
            'password' => 'whatever',
        ]);

        $wrongPassword = $this->postJson('/api/login', [
            'email' => env('ADMIN_EMAIL', 'admin@aura.local'),
            'password' => 'wrong-password',
        ]);

        $unknown->assertUnauthorized()
            ->assertExactJson(['message' => 'Credenciais inválidas.']);

        $wrongPassword->assertUnauthorized()
            ->assertExactJson(['message' => 'Credenciais inválidas.']);

        $this->assertSame(
            $unknown->json('message'),
            $wrongPassword->json('message')
        );

        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_blocks_user_endpoint(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->postJson('/api/login', [
            'email' => env('ADMIN_EMAIL', 'admin@aura.local'),
            'password' => env('ADMIN_PASSWORD', 'ChangeMeNow!123'),
        ])->assertOk();

        $this->assertAuthenticated();

        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest();

        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_public_registration_is_unavailable(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertNotFound();
    }

    public function test_login_rate_limit_is_active(): void
    {
        $this->seed(AdminUserSeeder::class);

        $email = env('ADMIN_EMAIL', 'admin@aura.local');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }
}
