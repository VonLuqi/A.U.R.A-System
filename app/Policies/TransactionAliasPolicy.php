<?php

namespace App\Policies;

use App\Models\TransactionAlias;
use App\Models\User;

/**
 * Owner-scoped aliases (PLAN_EXPANSAO §1.4 / §2.1).
 */
class TransactionAliasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('aliases.manage');
    }

    public function view(User $user, TransactionAlias $alias): bool
    {
        return $user->can('aliases.manage') && $this->owns($user, $alias);
    }

    public function create(User $user): bool
    {
        return $user->can('aliases.manage');
    }

    public function update(User $user, TransactionAlias $alias): bool
    {
        return $user->can('aliases.manage') && $this->owns($user, $alias);
    }

    public function delete(User $user, TransactionAlias $alias): bool
    {
        return $user->can('aliases.manage') && $this->owns($user, $alias);
    }

    private function owns(User $user, TransactionAlias $alias): bool
    {
        return (int) $user->id === (int) $alias->user_id;
    }
}
