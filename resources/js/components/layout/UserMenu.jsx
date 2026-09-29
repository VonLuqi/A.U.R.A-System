import { useState } from 'react';
import { LogOut } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import Button from '../ui/Button';

/**
 * UserMenu — Etapa D §1.4 / §2.2 / PLAN_EXPANSAO §8.1.
 * Nome + papel (caption) + Sair.
 */
export default function UserMenu() {
    const { user, logout } = useAuth();
    const [leaving, setLeaving] = useState(false);

    if (!user) {
        return null;
    }

    async function handleLogout() {
        if (leaving) {
            return;
        }

        setLeaving(true);

        try {
            await logout();
        } finally {
            setLeaving(false);
        }
    }

    const roleLabel = typeof user.role === 'string' ? user.role : 'user';

    return (
        <div className="flex items-center gap-3">
            <div className="hidden min-w-0 max-w-[10rem] text-right sm:block lg:max-w-[14rem]">
                <p className="truncate text-caption font-semibold text-ink">{user.name}</p>
                <p className="truncate text-small font-normal text-ink-secondary" title={user.email}>
                    {roleLabel}
                </p>
            </div>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                loading={leaving}
                disabled={leaving}
                aria-label="Sair"
                onClick={handleLogout}
            >
                <LogOut size={16} strokeWidth={1.75} aria-hidden />
                <span className="hidden sm:inline">Sair</span>
            </Button>
        </div>
    );
}
