import { useState } from 'react';
import { LogOut } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { userInitials } from '../../lib/auth';
import Button from '../ui/Button';

/**
 * UserMenu — Etapa D §1.4 / §2.2 · Etapa I §3.5.
 * Avatar / nome abre Conta; Sair ao lado. Modal fica no `TopNav`.
 *
 * @param {{
 *   onOpenAccount?: () => void,
 * }} [props]
 */
export default function UserMenu({ onOpenAccount }) {
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

    function openAccount() {
        onOpenAccount?.();
    }

    const roleLabel = typeof user.role === 'string' ? user.role : 'user';
    const initials = userInitials(user);

    return (
        <div className="flex items-center gap-2 sm:gap-3">
            <button
                type="button"
                className="flex min-w-0 items-center gap-2 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
                aria-label="Abrir minha conta"
                onClick={openAccount}
            >
                <span
                    className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border-subtle bg-surface-raised text-caption font-semibold text-ink"
                    aria-hidden
                >
                    {user.avatar_url ? (
                        <img
                            src={user.avatar_url}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        initials
                    )}
                </span>
                <div className="hidden min-w-0 max-w-[10rem] text-left sm:block lg:max-w-[14rem]">
                    <p className="truncate text-caption font-semibold text-ink">{user.name}</p>
                    <p className="truncate text-small font-normal text-ink-secondary" title={user.email}>
                        {roleLabel}
                    </p>
                </div>
            </button>

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
