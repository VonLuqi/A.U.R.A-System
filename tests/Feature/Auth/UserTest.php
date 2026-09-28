<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@aura.local',
        ]);

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk()
            ->assertExactJson([
                'user' => [
                    'id' => $user->id,
                    'name' => 'Admin',
                    'email' => 'admin@aura.local',
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
