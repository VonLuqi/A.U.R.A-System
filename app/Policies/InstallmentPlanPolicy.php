<?php

namespace App\Policies;

use App\Models\InstallmentPlan;
use App\Models\User;

/**
 * Owner-scoped installment plans (feature loans).
 */
class InstallmentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loans.manage');
    }

    public function view(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $plan);
    }

    public function create(User $user): bool
    {
        return $user->can('loans.manage');
    }

    public function update(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $plan);
    }

    public function delete(User $user, InstallmentPlan $plan): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $plan);
    }

    private function owns(User $user, InstallmentPlan $plan): bool
    {
        return (int) $user->id === (int) $plan->user_id;
    }
}
