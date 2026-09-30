<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Owner-scoped database notifications (PLAN_CARTOES_EMPRESTIMOS §5).
 * Registered explicitly — DatabaseNotification is not under App\Models.
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notifications.read');
    }

    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $user->can('notifications.read') && $this->owns($user, $notification);
    }

    /**
     * Mark as read (single).
     */
    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $user->can('notifications.read') && $this->owns($user, $notification);
    }

    private function owns(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === (int) $user->getKey();
    }
}
