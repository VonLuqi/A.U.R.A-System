<?php

namespace Tests\Feature\Expansion;

use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §9.1 — acceptance Feature suite (checklist smoke).
 *
 * Individual cases also live in focused suites; this file documents the
 * expansion DoD checklist in one place and guards regressions.
 */
class ExpansionAcceptanceChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        Storage::fake('statements');
    }

    public function test_visitor_cannot_list_users(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_other_user_gets_404_on_foreign_transaction(): void
    {
        $owner = User::factory()->admin()->create();
        $intruder = User::factory()->admin()->create();
        $tx = Transaction::factory()->manual()->for($owner)->create();

        $this->actingAs($intruder)
            ->getJson('/api/transactions/'.$tx->id)
            ->assertNotFound();
    }

    public function test_upload_quota_exhausted_returns_429(): void
    {
        $visitor = User::factory()->visitor()->create([
            'uploads_used' => 5,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->actingAs($visitor)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('nubank.csv', 10, 'text/csv'),
                'source' => 'nubank',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'usage_limit_exceeded')
            ->assertJsonPath('metric', 'uploads');
    }

    public function test_visitor_date_range_over_limit_returns_422(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/analytics/dashboard?from=2026-01-01&to=2026-04-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
