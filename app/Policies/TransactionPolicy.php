<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/**
 * Owner-scoped transactions (PLAN_EXPANSAO §2.1 / §3.1).
 *
 * Imported vs manual field restrictions are enforced in
 * `UpdateTransactionRequest` + `ManualTransactionService` (not only here):
 * non-admin cannot change amount/occurred_on/description/type on imports.
 * Delete is hard-delete; import counters are not rewritten.
 */
class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->canManage($user) && $this->owns($user, $transaction);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->canManage($user) && $this->owns($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->canManage($user) && $this->owns($user, $transaction);
    }

    private function owns(User $user, Transaction $transaction): bool
    {
        return (int) $user->id === (int) $transaction->user_id;
    }

    private function canManage(User $user): bool
    {
        return $user->can('transactions.manage');
    }
}
