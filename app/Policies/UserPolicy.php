<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Admin-only user management (PLAN_EXPANSAO §2.1 / §2.3).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $actor, User $model): bool
    {
        return $actor->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $actor, User $model): bool
    {
        return $actor->can('users.manage');
    }

    /**
     * Soft-block preferred; hard delete only for non-admin targets.
     */
    public function delete(User $actor, User $model): bool
    {
        if (! $actor->can('users.manage')) {
            return false;
        }

        // Never delete yourself or another admin via policy (soft-block instead).
        if ((int) $actor->id === (int) $model->id) {
            return false;
        }

        return $model->role !== UserRole::Admin;
    }
}
