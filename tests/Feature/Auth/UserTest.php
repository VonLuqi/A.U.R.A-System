<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@aura.local',
        ]);

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Admin')
            ->assertJsonPath('user.email', 'admin@aura.local')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.is_active', true)
            ->assertJsonStructure([
                'user' => [
                    'limits',
                    'usage',
                    'abilities',
                ],
            ]);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_user_endpoint_returns_json_even_without_accept_header(): void
    {
        $response = $this->get('/api/user');

        $response->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
