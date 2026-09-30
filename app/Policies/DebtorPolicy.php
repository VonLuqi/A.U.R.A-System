<?php

namespace App\Policies;

use App\Models\Debtor;
use App\Models\User;

/**
 * Owner-scoped debtors / pessoas (reuses loans.manage).
 */
class DebtorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loans.manage');
    }

    public function view(User $user, Debtor $debtor): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $debtor);
    }

    public function create(User $user): bool
    {
        return $user->can('loans.manage');
    }

    public function update(User $user, Debtor $debtor): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $debtor);
    }

    public function delete(User $user, Debtor $debtor): bool
    {
        return $user->can('loans.manage') && $this->owns($user, $debtor);
    }

    private function owns(User $user, Debtor $debtor): bool
    {
        return (int) $user->id === (int) $debtor->user_id;
    }
}
