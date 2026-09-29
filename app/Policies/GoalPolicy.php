<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

/**
 * Owner-scoped goals (PLAN_EXPANSAO §1.3 / §2.1).
 */
class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('goals.manage');
    }

    public function view(User $user, Goal $goal): bool
    {
        return $user->can('goals.manage') && $this->owns($user, $goal);
    }

    public function create(User $user): bool
    {
        return $user->can('goals.manage');
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->can('goals.manage') && $this->owns($user, $goal);
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $user->can('goals.manage') && $this->owns($user, $goal);
    }

    private function owns(User $user, Goal $goal): bool
    {
        return (int) $user->id === (int) $goal->user_id;
    }
}
