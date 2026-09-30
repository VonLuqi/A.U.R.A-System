<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

/**
 * Owner-scoped loans / cobranças (PLAN_CARTOES_EMPRESTIMOS §2.3).
 * Auto-discovered by Laravel (Loan → LoanPolicy).
 */
class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loans.manage');
    }

    public function view(User $user, Loan $loan): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $loan);
    }

    public function create(User $user): bool
    {
        return $user->can('loans.manage');
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $loan);
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $loan);
    }

    private function owns(User $user, Loan $loan): bool
    {
        return (int) $user->id === (int) $loan->user_id;
    }
}
