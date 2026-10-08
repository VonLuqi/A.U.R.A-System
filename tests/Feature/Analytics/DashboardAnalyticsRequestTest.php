<?php

namespace Tests\Feature\Analytics;

use App\Http\Requests\Analytics\DashboardAnalyticsRequest;
use App\Models\Category;
use App\Models\CreditCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §5.5.1 / PLAN_EXPANSAO §6.1 — DashboardAnalyticsRequest + presets + resolveGroupBy.
 */
class DashboardAnalyticsRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
    }

    public function test_defaults_to_current_month_and_resolves_group_by_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();

        try {
            // September = 30 days ≤ 45 → group_by=day (no more hardcoded month).
            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard')
                ->assertOk()
                ->assertJsonPath('data.filters.from', '2026-09-01')
                ->assertJsonPath('data.filters.to', '2026-09-30')
                ->assertJsonPath('data.filters.group_by', 'day')
                ->assertJsonPath('data.filters.preset', null)
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
                'preset' => 'custom',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.from', '2026-08-01')
            ->assertJsonPath('data.filters.to', '2026-08-31')
            ->assertJsonPath('data.filters.type', 'debit')
            ->assertJsonPath('data.filters.category_id', $category->id)
            ->assertJsonPath('data.filters.q', 'ifood')
            ->assertJsonPath('data.filters.group_by', 'day')
            ->assertJsonPath('data.filters.preset', 'custom');
    }

    public function test_preset_last_30_computes_bounds_and_group_by_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->create();

        try {
            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard?preset=last_30')
                ->assertOk()
                ->assertJsonPath('data.filters.from', '2026-08-17')
                ->assertJsonPath('data.filters.to', '2026-09-15')
                ->assertJsonPath('data.filters.preset', 'last_30')
                ->assertJsonPath('data.filters.group_by', 'day');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_preset_custom_without_dates_returns_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?preset=custom')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_wide_range_without_group_by_resolves_to_month(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?from=2026-01-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonPath('data.filters.group_by', 'month');
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

    public function test_rejects_invalid_preset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?preset=ytd')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preset']);
    }

    public function test_preset_all_clears_dates_and_uses_group_by_month(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?preset=all')
            ->assertOk()
            ->assertJsonPath('data.filters.from', null)
            ->assertJsonPath('data.filters.to', null)
            ->assertJsonPath('data.filters.preset', 'all')
            ->assertJsonPath('data.filters.group_by', 'month');
    }

    public function test_visitor_preset_all_returns_422(): void
    {
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($visitor)
            ->getJson('/api/analytics/dashboard?preset=all')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preset']);
    }

    public function test_current_month_bounds_helper_uses_app_timezone(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo']);
        $at = CarbonImmutable::parse('2026-02-10 23:00:00', 'UTC');

        [$from, $to] = DashboardAnalyticsRequest::currentMonthBounds($at->timezone('America/Sao_Paulo'));

        $this->assertSame('2026-02-01', $from);
        $this->assertSame('2026-02-28', $to);
    }

    public function test_preset_my_cycle_uses_expense_day_and_switches_on_type(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 12:00:00', 'America/Sao_Paulo'));
        config(['app.timezone' => 'America/Sao_Paulo']);

        $user = User::factory()->admin()->create([
            'expense_cycle_day' => 6,
            'income_cycle_day' => 12,
        ]);

        try {
            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard?preset=my_cycle&type=debit')
                ->assertOk()
                ->assertJsonPath('data.filters.from', '2026-10-06')
                ->assertJsonPath('data.filters.to', '2026-11-06')
                ->assertJsonPath('data.filters.preset', 'my_cycle');

            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard?preset=my_cycle&type=credit')
                ->assertOk()
                ->assertJsonPath('data.filters.from', '2026-09-12')
                ->assertJsonPath('data.filters.to', '2026-10-12')
                ->assertJsonPath('data.filters.preset', 'my_cycle');

            $this->actingAs($user)
                ->getJson('/api/analytics/dashboard?preset=my_cycle&cycle_offset=-1')
                ->assertOk()
                ->assertJsonPath('data.filters.from', '2026-09-06')
                ->assertJsonPath('data.filters.to', '2026-10-06');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_credit_card_ids_and_include_uncarded_are_accepted(): void
    {
        $user = User::factory()->admin()->create();
        $cardA = CreditCard::factory()->create(['user_id' => $user->id]);
        $cardB = CreditCard::factory()->create(['user_id' => $user->id]);
        $foreign = CreditCard::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?'.http_build_query([
                'credit_card_ids' => [$cardA->id, $cardB->id],
                'include_uncarded' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.credit_card_ids', [$cardA->id, $cardB->id])
            ->assertJsonPath('data.filters.include_uncarded', true);

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?credit_card_ids='.$foreign->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_card_ids.0']);

        $this->actingAs($user)
            ->getJson('/api/analytics/dashboard?preset=card_cycle')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preset']);
    }
}
