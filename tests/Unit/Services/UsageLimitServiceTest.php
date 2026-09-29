<?php

namespace Tests\Unit\Services;

use App\Exceptions\UsageLimitExceededException;
use App\Models\User;
use App\Services\UsageLimitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §2.4 — UsageLimitService business rules.
 */
class UsageLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private UsageLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Sao_Paulo']);
        $this->service = app(UsageLimitService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reset_period_initializes_null_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->visitor()->create([
            'quota_period_starts_at' => null,
            'uploads_used' => 3,
            'manual_transactions_used' => 2,
        ]);

        $this->service->resetPeriodIfNeeded($user);
        $user->refresh();

        $this->assertSame(0, $user->uploads_used);
        $this->assertSame(0, $user->manual_transactions_used);
        $this->assertSame(
            '2026-09-01',
            $user->quota_period_starts_at?->timezone('America/Sao_Paulo')->format('Y-m-d')
        );
    }

    public function test_reset_period_rolls_counters_on_new_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 08:00:00', 'America/Sao_Paulo'));

        $user = User::factory()->visitor()->create([
            'quota_period_starts_at' => Carbon::parse('2026-08-01', 'America/Sao_Paulo'),
            'uploads_used' => 5,
            'manual_transactions_used' => 20,
        ]);

        $this->service->resetPeriodIfNeeded($user);
        $user->refresh();

        $this->assertSame(0, $user->uploads_used);
        $this->assertSame(0, $user->manual_transactions_used);
        $this->assertSame(
            '2026-09-01',
            $user->quota_period_starts_at?->timezone('America/Sao_Paulo')->format('Y-m-d')
        );
    }

    public function test_assert_can_throws_when_upload_limit_exhausted(): void
    {
        $user = User::factory()->visitor()->create([
            'uploads_used' => 5,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->expectException(UsageLimitExceededException::class);

        $this->service->assertCan($user, UsageLimitService::METRIC_UPLOADS);
    }

    public function test_admin_uploads_are_unlimited(): void
    {
        $user = User::factory()->admin()->create([
            'uploads_used' => 999,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->service->assertCan($user, UsageLimitService::METRIC_UPLOADS);
        $this->assertNull($this->service->remaining($user, UsageLimitService::METRIC_UPLOADS));
    }

    public function test_increment_bumps_manual_transactions_counter(): void
    {
        $user = User::factory()->visitor()->create([
            'manual_transactions_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->service->increment($user, UsageLimitService::METRIC_MANUAL_TRANSACTIONS);

        $this->assertSame(1, $user->fresh()->manual_transactions_used);
    }

    public function test_date_range_limit_for_visitor_and_admin(): void
    {
        $visitor = User::factory()->visitor()->create();
        $admin = User::factory()->admin()->create();

        $this->assertSame(90, $this->service->dateRangeDaysLimit($visitor));
        $this->assertSame(0, $this->service->dateRangeDaysLimit($admin));
        $this->assertTrue($this->service->isDateRangeAllowed($visitor, '2026-01-01', '2026-03-31'));
        $this->assertFalse($this->service->isDateRangeAllowed($visitor, '2026-01-01', '2026-04-01'));
        $this->assertTrue($this->service->isDateRangeAllowed($admin, '2020-01-01', '2026-12-31'));
        $this->assertSame(90, $this->service->inclusiveDaySpan('2026-01-01', '2026-03-31'));
        $this->assertSame(91, $this->service->inclusiveDaySpan('2026-01-01', '2026-04-01'));
    }
}
