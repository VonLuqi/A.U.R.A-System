<?php

namespace App\Policies;

use App\Models\StatementImport;
use App\Models\User;

/**
 * Defense-in-depth for statement imports (Etapa C §6.3).
 * Route binding already scopes by owner (§6.2); policy guards controller entry.
 */
class StatementImportPolicy
{
    /**
     * Authenticated users may list their own imports (query scopes ownership).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Only the owner may view a given import.
     */
    public function view(User $user, StatementImport $statementImport): bool
    {
        return (int) $user->id === (int) $statementImport->user_id;
    }
}
