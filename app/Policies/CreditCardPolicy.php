<?php

namespace App\Policies;

use App\Models\CreditCard;
use App\Models\User;

/**
 * Owner-scoped credit cards (PLAN_CARTOES_EMPRESTIMOS §2.3).
 * Auto-discovered by Laravel (CreditCard → CreditCardPolicy).
 */
class CreditCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('credit_cards.manage');
    }

    public function view(User $user, CreditCard $creditCard): bool
    {
        return $user->can('credit_cards.manage') && $this->owns($user, $creditCard);
    }

    public function create(User $user): bool
    {
        return $user->can('credit_cards.manage');
    }

    public function update(User $user, CreditCard $creditCard): bool
    {
        return $user->can('credit_cards.manage') && $this->owns($user, $creditCard);
    }

    public function delete(User $user, CreditCard $creditCard): bool
    {
        return $user->can('credit_cards.manage') && $this->owns($user, $creditCard);
    }

    private function owns(User $user, CreditCard $creditCard): bool
    {
        return (int) $user->id === (int) $creditCard->user_id;
    }
}
