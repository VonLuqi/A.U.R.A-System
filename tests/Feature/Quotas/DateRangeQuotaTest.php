<?php

namespace Tests\Feature\Quotas;

use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §2.4 — date-range amplitude capped by role.
 */
class DateRangeQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
    }

    public function test_visitor_rejected_when_analytics_range_exceeds_90_days(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/analytics/dashboard?from=2026-01-01&to=2026-04-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_visitor_accepted_at_exactly_90_days(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/analytics/dashboard?from=2026-01-01&to=2026-03-31')
            ->assertOk();
    }

    public function test_test_role_rejected_when_transactions_range_exceeds_60_days(): void
    {
        $test = User::factory()->test()->create();

        $this->actingAs($test)
            ->getJson('/api/transactions?from=2026-01-01&to=2026-03-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    public function test_admin_allows_wide_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson('/api/analytics/dashboard?from=2020-01-01&to=2026-12-31')
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/api/transactions?from=2020-01-01&to=2026-12-31')
            ->assertOk();
    }

    public function test_transactions_require_from_and_to_together(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/transactions?from=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
