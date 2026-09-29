<?php

namespace Tests\Feature\Auth;

use App\Models\Goal;
use App\Models\Transaction;
use App\Models\TransactionAlias;
use App\Models\User;
use App\Services\UsageLimitService;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RbacAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        Storage::fake('statements');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->admin()->inactive()->create([
            'email' => 'inactive@aura.local',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Conta desativada.');

        $this->assertGuest();
    }

    public function test_inactive_authenticated_user_is_blocked_by_active_middleware(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'Conta desativada.');
    }

    public function test_gates_follow_ability_matrix(): void
    {
        $admin = User::factory()->admin()->create();
        $visitor = User::factory()->visitor()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('users.manage'));
        $this->assertFalse(Gate::forUser($visitor)->allows('users.manage'));
        $this->assertTrue(Gate::forUser($visitor)->allows('transactions.manage'));
        $this->assertTrue(Gate::forUser($visitor)->allows('aliases.manage'));
    }

    public function test_role_middleware_allows_admin_only(): void
    {
        RouteFacadeProbe::registerRoleProbe();

        $admin = User::factory()->admin()->create();
        $visitor = User::factory()->visitor()->create();

        $this->actingAs($admin)
            ->getJson('/api/__probe/role-admin')
            ->assertOk();

        $this->actingAs($visitor)
            ->getJson('/api/__probe/role-admin')
            ->assertForbidden();
    }

    public function test_quota_middleware_returns_429_when_upload_limit_exhausted(): void
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
            ->assertJsonPath('metric', 'uploads')
            ->assertJsonPath('limit', 5)
            ->assertJsonPath('used', 5);
    }

    public function test_user_helpers_can_upload_and_remaining(): void
    {
        $visitor = User::factory()->visitor()->create([
            'uploads_used' => 2,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $this->assertTrue($visitor->canUpload());
        $this->assertSame(3, $visitor->remainingUploads());

        $admin = User::factory()->admin()->create();
        $this->assertTrue($admin->canUpload());
        $this->assertNull($admin->remainingUploads());
    }

    public function test_route_bindings_scope_goal_transaction_and_alias_to_owner(): void
    {
        RouteFacadeProbe::registerOwnerProbes();

        $owner = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $goal = Goal::factory()->for($owner)->create();
        $tx = Transaction::factory()->for($owner)->create();
        $alias = TransactionAlias::factory()->for($owner)->create();

        $this->actingAs($owner)->getJson('/api/__probe/goals/'.$goal->id)->assertOk();
        $this->actingAs($other)->getJson('/api/__probe/goals/'.$goal->id)->assertNotFound();

        $this->actingAs($owner)->getJson('/api/__probe/transactions/'.$tx->id)->assertOk();
        $this->actingAs($other)->getJson('/api/__probe/transactions/'.$tx->id)->assertNotFound();

        $this->actingAs($owner)->getJson('/api/__probe/aliases/'.$alias->id)->assertOk();
        $this->actingAs($other)->getJson('/api/__probe/aliases/'.$alias->id)->assertNotFound();
    }

    public function test_usage_limit_service_increments_after_successful_assert(): void
    {
        $user = User::factory()->visitor()->create([
            'uploads_used' => 0,
            'quota_period_starts_at' => now()->startOfMonth(),
        ]);

        $service = app(UsageLimitService::class);
        $service->assertCan($user, UsageLimitService::METRIC_UPLOADS);
        $service->increment($user, UsageLimitService::METRIC_UPLOADS);

        $this->assertSame(1, $user->fresh()->uploads_used);
    }
}

/**
 * Registers ephemeral probe routes inside the api+web stack for middleware tests.
 */
final class RouteFacadeProbe
{
    public static function registerRoleProbe(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'active', 'role:admin'])
            ->get('/api/__probe/role-admin', fn () => response()->json(['ok' => true]));
    }

    public static function registerOwnerProbes(): void
    {
        $web = ['web', 'auth', 'active'];

        \Illuminate\Support\Facades\Route::middleware($web)
            ->get('/api/__probe/goals/{goal}', fn (Goal $goal) => response()->json(['id' => $goal->id]));

        \Illuminate\Support\Facades\Route::middleware($web)
            ->get('/api/__probe/transactions/{transaction}', fn (Transaction $transaction) => response()->json(['id' => $transaction->id]));

        \Illuminate\Support\Facades\Route::middleware($web)
            ->get('/api/__probe/aliases/{alias}', fn (TransactionAlias $alias) => response()->json(['id' => $alias->id]));
    }
}
