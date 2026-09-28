<?php

namespace Tests\Feature\Analytics;

use App\Http\Requests\Analytics\DashboardAnalyticsRequest;
use App\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.5.1 — DashboardAnalyticsRequest query validation + current-month defaults.
 */
class DashboardAnalyticsRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
    }

    public function test_defaults_to_current_month_and_group_by_month(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();

        try {
            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard')
                ->assertOk()
                ->assertJsonPath('data.filters.from', '2026-09-01')
                ->assertJsonPath('data.filters.to', '2026-09-30')
                ->assertJsonPath('data.filters.group_by', 'month')
                ->assertJsonPath('data.filters.type', null);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_accepts_explicit_range_and_filters(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?'.http_build_query([
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'type' => 'debit',
                'category_id' => $category->id,
                'q' => 'ifood',
                'group_by' => 'day',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.from', '2026-08-01')
            ->assertJsonPath('data.filters.to', '2026-08-31')
            ->assertJsonPath('data.filters.type', 'debit')
            ->assertJsonPath('data.filters.category_id', $category->id)
            ->assertJsonPath('data.filters.q', 'ifood')
            ->assertJsonPath('data.filters.group_by', 'day');
    }

    public function test_rejects_partial_date_range_and_invalid_group_by(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-01&group_by=week')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to', 'group_by']);
    }

    public function test_rejects_inverted_dates_and_invalid_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-09-30&to=2026-09-01&type=transfer')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to', 'type']);
    }

    public function test_current_month_bounds_helper_uses_app_timezone(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-02-10 23:00:00', 'UTC');

        [$from, $to] = DashboardAnalyticsRequest::currentMonthBounds($at->timezone('America/Sao_Paulo'));

        $this->assertSame('2026-02-01', $from);
        $this->assertSame('2026-02-28', $to);
    }
}
