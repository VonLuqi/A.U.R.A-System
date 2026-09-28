<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;
    public function test_public_api_registration_route_does_not_exist(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        // /api/* is excluded from the SPA catch-all → true 404.
        $response->assertNotFound();
    }

    public function test_web_register_post_is_not_accepted(): void
    {
        $response = $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        // GET /register may hit the SPA catch-all; POST must not create a user (405/404).
        $this->assertContains(
            $response->status(),
            [404, 405],
            'Public POST /register must not succeed'
        );
    }
}
