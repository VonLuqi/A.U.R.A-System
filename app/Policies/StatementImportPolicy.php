<?php

namespace App\Policies;

use App\Models\StatementImport;
use App\Models\User;

/**
 * Defense-in-depth for statement imports (Etapa C §6.3 / PLAN_EXPANSAO §2.1).
 * Route binding already scopes by owner; policy guards controller entry.
 */
class StatementImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('statements.upload') || $user->can('transactions.manage');
    }

    public function view(User $user, StatementImport $statementImport): bool
    {
        return $this->owns($user, $statementImport);
    }

    public function create(User $user): bool
    {
        return $user->can('statements.upload');
    }

    public function delete(User $user, StatementImport $statementImport): bool
    {
        return $this->owns($user, $statementImport) && $user->can('statements.upload');
    }

    private function owns(User $user, StatementImport $statementImport): bool
    {
        return (int) $user->id === (int) $statementImport->user_id;
    }
}
