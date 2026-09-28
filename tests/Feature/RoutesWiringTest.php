<?php

namespace Tests\Feature;

use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §6.2 — API wiring: SPA catch-all must not swallow /api/*, owner-scoped binding.
 */
class RoutesWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_api_path_returns_json_404_not_spa_shell(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist');

        $response->assertNotFound();
        $this->assertStringNotContainsString('id="app"', $response->getContent());
        $this->assertStringNotContainsString('@vite', $response->getContent());
    }

    public function test_spa_catch_all_serves_shell_for_frontend_paths(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_statement_import_binding_scopes_to_authenticated_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = StatementImport::factory()->for($owner)->create([
            'original_filename' => 'mine.csv',
        ]);

        $this->actingAs($owner)
            ->getJson('/api/statements/'.$import->id)
            ->assertOk()
            ->assertJsonPath('data.original_filename', 'mine.csv');

        $this->actingAs($other)
            ->getJson('/api/statements/'.$import->id)
            ->assertNotFound();
    }
}
