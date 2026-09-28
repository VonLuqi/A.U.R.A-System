<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\StatementStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Named limiters + LoginRequest hits use the array cache store in phpunit.xml.
        Cache::flush();
        Storage::fake(StatementStorage::DISK);
    }

    public function test_sixth_failed_login_within_a_minute_returns_429(): void
    {
        User::factory()->create([
            'email' => 'ratelimit@aura.local',
            'password' => 'ChangeMeNow!123',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'ratelimit@aura.local',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $response = $this->postJson('/api/login', [
            'email' => 'ratelimit@aura.local',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure(['message'])
            ->assertHeader('Retry-After');
    }

    public function test_statements_upload_beyond_limit_returns_429(): void
    {
        $user = User::factory()->create();
        $limit = (int) env('RATE_LIMIT_UPLOAD_PER_USER', 10);

        for ($i = 0; $i < $limit; $i++) {
            // Fake empty CSV passes FormRequest then fails parse (422) — still counts for throttle.
            $this->actingAs($user)
                ->postJson('/api/statements/upload', [
                    'file' => UploadedFile::fake()->create('nubank.csv', 10, 'text/csv'),
                ])
                ->assertUnprocessable();
        }

        $response = $this->actingAs($user)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('nubank.csv', 10, 'text/csv'),
            ]);

        $response->assertStatus(429)
            ->assertExactJson([
                'message' => 'Limite de uploads excedido. Tente novamente em breve.',
            ])
            ->assertHeader('Retry-After');
    }
}
