<?php

namespace Tests\Unit\Config;

use App\Enums\UserRole;
use Tests\TestCase;

class AuraConfigTest extends TestCase
{
    public function test_multi_tenant_strategy_is_shared_db_row_level(): void
    {
        $this->assertSame('shared_db_row_level', config('aura.multi_tenant.strategy'));
        $this->assertSame('user_id', config('aura.multi_tenant.owner_column'));
    }

    public function test_abilities_matrix_covers_required_gates(): void
    {
        $abilities = config('aura.abilities');

        $this->assertSame([UserRole::Admin->value], $abilities['users.manage']);
        $this->assertContains(UserRole::Admin->value, $abilities['transactions.manage']);
        $this->assertContains(UserRole::Visitor->value, $abilities['statements.upload']);
        $this->assertContains(UserRole::Subadmin->value, $abilities['goals.manage']);
        $this->assertSame(
            [
                UserRole::Admin->value,
                UserRole::Subadmin->value,
                UserRole::Visitor->value,
                UserRole::Test->value,
            ],
            $abilities['aliases.manage']
        );
        $this->assertContains(UserRole::Visitor->value, $abilities['aliases.manage']);
        $this->assertSame($abilities['aliases.manage'], $abilities['credit_cards.manage']);
        $this->assertSame($abilities['aliases.manage'], $abilities['loans.manage']);
        $this->assertSame($abilities['aliases.manage'], $abilities['notifications.read']);
    }

    public function test_default_limits_match_expansion_contract(): void
    {
        $this->assertSame(0, config('aura.limits.admin.max_uploads'));
        $this->assertSame(50, config('aura.limits.subadmin.max_uploads'));
        $this->assertSame(5, config('aura.limits.visitor.max_uploads'));
        $this->assertSame(3, config('aura.limits.test.max_uploads'));
        $this->assertSame(20, config('aura.limits.visitor.max_manual_transactions'));
        $this->assertSame(10, config('aura.limits.test.max_manual_transactions'));
        $this->assertSame(90, config('aura.limits.visitor.max_date_range_days'));
        $this->assertSame(60, config('aura.limits.test.max_date_range_days'));
        $this->assertSame(500, config('aura.aliases.retroactive_limit'));
        $this->assertSame(0, config('aura.limits.admin.max_credit_cards'));
        $this->assertSame(3, config('aura.limits.visitor.max_credit_cards'));
        $this->assertSame(5, config('aura.limits.visitor.max_loans'));
        $this->assertSame(2, config('aura.limits.test.max_credit_cards'));
        $this->assertSame(3, config('aura.limits.test.max_loans'));
    }

    public function test_quota_enforced_roles_are_visitor_and_test(): void
    {
        $this->assertSame(
            [UserRole::Visitor->value, UserRole::Test->value],
            config('aura.quota_enforced_roles')
        );
    }

    public function test_feature_flags_defaults_and_ability_map(): void
    {
        $this->assertTrue(config('aura.features.manual_transactions'));
        $this->assertTrue(config('aura.features.goals'));
        $this->assertTrue(config('aura.features.aliases'));
        $this->assertTrue(config('aura.features.credit_card_upload'));
        $this->assertTrue(config('aura.features.admin_users'));
        $this->assertFalse(config('aura.features.credit_cards'));
        $this->assertFalse(config('aura.features.loans'));
        $this->assertFalse(config('aura.features.notifications'));
        $this->assertSame('goals', config('aura.ability_features')['goals.manage']);
        $this->assertNull(config('aura.ability_features')['statements.upload']);
        $this->assertSame('credit_cards', config('aura.ability_features')['credit_cards.manage']);
        $this->assertSame('loans', config('aura.ability_features')['loans.manage']);
        $this->assertSame('notifications', config('aura.ability_features')['notifications.read']);
    }

    public function test_notification_windows_default_to_three_days(): void
    {
        $this->assertSame(3, config('aura.notifications.credit_card_due_days'));
        $this->assertSame(3, config('aura.notifications.loan_due_days'));
    }
}