<?php

namespace Tests\Feature\Expansion;

use App\Models\User;
use Database\Seeders\RoleLimitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PLAN_EXPANSAO §9.3 — feature flags / gradual rollout.
 */
class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleLimitsSeeder::class);
        Storage::fake('statements');
    }

    public function test_disabled_goals_feature_denies_gate_even_for_admin(): void
    {
        config(['aura.features.goals' => false]);

        $admin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($admin)->allows('goals.manage'));
        $this->assertTrue(Gate::forUser($admin)->allows('statements.upload'));
    }

    public function test_auth_payload_exposes_features_and_omits_disabled_ability(): void
    {
        config([
            'aura.features.aliases' => false,
            'aura.features.goals' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.features.aliases', false)
            ->assertJsonPath('user.features.goals', true);

        $abilities = $response->json('user.abilities');
        $this->assertNotContains('aliases.manage', $abilities);
        $this->assertContains('goals.manage', $abilities);
    }

    public function test_credit_card_upload_rejected_when_feature_disabled(): void
    {
        config(['aura.features.credit_card_upload' => false]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/statements/upload', [
                'file' => UploadedFile::fake()->create('fatura.csv', 10, 'text/csv'),
                'source' => 'nubank_credit',
                'statement_kind' => 'credit_card',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statement_kind']);
    }

    public function test_etapa_h_features_gate_named_abilities(): void
    {
        config([
            'aura.features.credit_cards' => true,
            'aura.features.loans' => false,
            'aura.features.notifications' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('credit_cards.manage'));
        $this->assertFalse(Gate::forUser($admin)->allows('loans.manage'));
        $this->assertTrue(Gate::forUser($admin)->allows('notifications.read'));

        $response = $this->actingAs($admin)->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('user.features.credit_cards', true)
            ->assertJsonPath('user.features.loans', false)
            ->assertJsonPath('user.features.notifications', true);

        $abilities = $response->json('user.abilities');
        $this->assertContains('credit_cards.manage', $abilities);
        $this->assertNotContains('loans.manage', $abilities);
        $this->assertContains('notifications.read', $abilities);
    }

    public function test_etapa_h_http_routes_return_feature_disabled(): void
    {
        config([
            'aura.features.credit_cards' => false,
            'aura.features.loans' => false,
            'aura.features.notifications' => false,
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/credit-cards', [
                'name' => 'X',
                'closing_day' => 1,
                'due_day' => 10,
            ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature', 'credit_cards');

        $this->actingAs($admin)
            ->postJson('/api/loans', [
                'debtor_name' => 'Y',
                'kind' => 'cash',
                'amount' => '10.00',
                'lent_on' => '2026-09-01',
                'due_on' => '2026-09-10',
            ])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature', 'loans');

        $this->actingAs($admin)
            ->getJson('/api/notifications')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'feature_disabled')
            ->assertJsonPath('feature', 'notifications');

        $this->assertFalse(Gate::forUser($admin)->allows('notifications.read'));
    }
}
