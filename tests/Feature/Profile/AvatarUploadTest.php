<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PLAN_PERFIL_BRANDING §6.1 — POST/DELETE /api/profile/avatar.
 */
class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        Storage::fake('public');
    }

    public function test_guest_cannot_upload_or_delete_avatar(): void
    {
        $this->postJson('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertUnauthorized();

        $this->deleteJson('/api/profile/avatar')->assertUnauthorized();
    }

    public function test_upload_valid_jpeg_png_webp_stores_on_public_disk(): void
    {
        $user = User::factory()->create();

        foreach (['avatar.jpg', 'avatar.png', 'avatar.webp'] as $filename) {
            $response = $this->actingAs($user)->post('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->image($filename),
            ], [
                'Accept' => 'application/json',
            ]);

            $response->assertCreated()
                ->assertJsonPath('user.id', $user->id);

            $avatarUrl = $response->json('user.avatar_url');
            $this->assertIsString($avatarUrl);
            $this->assertNotSame('', $avatarUrl);
            $this->assertStringContainsString('/storage/avatars/'.$user->id.'/', $avatarUrl);
            $this->assertStringNotContainsString('avatar_path', (string) json_encode($response->json('user')));

            $path = $user->fresh()->avatar_path;
            $this->assertIsString($path);
            $this->assertStringStartsWith('avatars/'.$user->id.'/', $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_replacing_avatar_deletes_previous_file(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('first.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $first->assertCreated();
        $oldPath = $user->fresh()->avatar_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        $second = $this->actingAs($user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('second.png'),
        ], [
            'Accept' => 'application/json',
        ]);

        $second->assertCreated();
        $newPath = $user->fresh()->avatar_path;

        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertNotNull($second->json('user.avatar_url'));
    }

    public function test_delete_avatar_removes_file_and_clears_path(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('keep-me.webp'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $path = $user->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->deleteJson('/api/profile/avatar')
            ->assertOk()
            ->assertJsonPath('user.avatar_url', null)
            ->assertJsonMissingPath('user.avatar_path');

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_invalid_mime_and_oversized_avatar_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('evil.gif', 20, 'image/gif'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);

        $this->actingAs($user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(2049),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['avatar']);

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_auth_user_resource_includes_non_null_avatar_url_after_upload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('face.jpg'),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson('/api/user');

        $response->assertOk();
        $avatarUrl = $response->json('user.avatar_url');
        $this->assertIsString($avatarUrl);
        $this->assertStringContainsString('/storage/avatars/'.$user->id.'/', $avatarUrl);
    }
}
