<?php

namespace Tests\Unit\Policies;

use App\Models\StatementImport;
use App\Models\User;
use App\Policies\StatementImportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §6.3 — StatementImportPolicy ownership rules.
 */
class StatementImportPolicyTest extends TestCase
{
    use RefreshDatabase;

    private StatementImportPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new StatementImportPolicy;
    }

    public function test_view_any_allows_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_allows_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = StatementImport::factory()->for($owner)->create();

        $this->assertTrue($this->policy->view($owner, $import));
        $this->assertFalse($this->policy->view($other, $import));
    }

    public function test_controller_authorizes_via_policy_auto_discovery(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $import = StatementImport::factory()->for($owner)->create();

        $this->assertTrue($owner->can('view', $import));
        $this->assertTrue($owner->can('viewAny', StatementImport::class));
        $this->assertFalse($other->can('view', $import));
    }
}
