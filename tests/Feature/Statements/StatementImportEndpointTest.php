<?php

namespace Tests\Feature\Statements;

use App\Models\StatementImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.7 — GET /api/statements (+ show).
 */
class StatementImportEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/statements')->assertUnauthorized();
    }

    public function test_show_requires_authentication(): void
    {
        $import = StatementImport::factory()->create();

        $this->getJson('/api/statements/'.$import->id)->assertUnauthorized();
    }

    public function test_index_lists_own_imports_newest_first_with_meta(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $older = StatementImport::factory()->for($user)->create([
            'original_filename' => 'older.csv',
            'created_at' => now()->subDay(),
        ]);
        $newer = StatementImport::factory()->for($user)->create([
            'original_filename' => 'newer.csv',
            'created_at' => now(),
        ]);
        StatementImport::factory()->for($other)->create([
            'original_filename' => 'other.csv',
        ]);

        $response = $this->actingAs($user)->getJson('/api/statements');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'original_filename',
                        'format',
                        'source',
                        'status',
                        'counters' => ['rows_total', 'rows_imported', 'rows_skipped'],
                        'checksum',
                        'created_at',
                        'error_message',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $payload = json_encode($response->json());
        $this->assertStringNotContainsString('stored_path', (string) $payload);
        $this->assertStringNotContainsString('other.csv', (string) $payload);
    }

    public function test_index_paginates_at_20_per_page(): void
    {
        $user = User::factory()->create();
        StatementImport::factory()->count(21)->for($user)->create();

        $this->actingAs($user)
            ->getJson('/api/statements')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.last_page', 2);

        $this->actingAs($user)
            ->getJson('/api/statements?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_show_returns_detail_with_counters_and_omits_stored_path(): void
    {
        $user = User::factory()->create();
        $import = StatementImport::factory()->for($user)->create([
            'original_filename' => 'NU_123.csv',
            'format' => 'csv',
            'source' => 'nubank',
            'status' => StatementImport::STATUS_COMPLETED,
            'rows_total' => 120,
            'rows_imported' => 100,
            'rows_skipped' => 20,
            'checksum' => 'abc123',
            'error_message' => null,
            'stored_path' => '2026/09/1/uuid_NU_123.csv',
        ]);

        $response = $this->actingAs($user)->getJson('/api/statements/'.$import->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.original_filename', 'NU_123.csv')
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.source', 'nubank')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.counters.rows_total', 120)
            ->assertJsonPath('data.counters.rows_imported', 100)
            ->assertJsonPath('data.counters.rows_skipped', 20)
            ->assertJsonPath('data.checksum', 'abc123')
            ->assertJsonPath('data.error_message', null);

        $this->assertArrayNotHasKey('stored_path', $response->json('data'));
        $this->assertStringNotContainsString('uuid_NU_123', (string) json_encode($response->json()));
    }

    public function test_show_includes_error_message_only_when_failed(): void
    {
        $user = User::factory()->create();
        $failed = StatementImport::factory()->for($user)->failed()->create([
            'error_message' => 'Não foi possível ler o extrato.',
        ]);
        $completed = StatementImport::factory()->for($user)->create([
            'status' => StatementImport::STATUS_COMPLETED,
            'error_message' => 'should stay hidden',
        ]);

        $this->actingAs($user)
            ->getJson('/api/statements/'.$failed->id)
            ->assertOk()
            ->assertJsonPath('data.error_message', 'Não foi possível ler o extrato.');

        $this->actingAs($user)
            ->getJson('/api/statements/'.$completed->id)
            ->assertOk()
            ->assertJsonPath('data.error_message', null);
    }

    public function test_show_returns_404_for_other_users_import(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = StatementImport::factory()->for($owner)->create();

        $this->actingAs($other)
            ->getJson('/api/statements/'.$import->id)
            ->assertNotFound();
    }

    public function test_show_returns_404_for_missing_import(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/statements/999999')
            ->assertNotFound();
    }
}
