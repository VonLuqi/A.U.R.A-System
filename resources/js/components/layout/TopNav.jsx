import { useEffect, useId, useState } from 'react';
import { Menu, X } from 'lucide-react';
import { NavLink, useLocation } from 'react-router-dom';
import BrandMark from '../ui/BrandMark';
import UserMenu from './UserMenu';

const NAV_ITEMS = [
    { to: '/dashboard', label: 'Visão geral', end: true },
    { to: '/upload', label: 'Importar', end: false },
];

function navLinkClass({ isActive }) {
    return [
        'rounded-sm text-body-lg no-underline transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
        isActive
            ? 'font-semibold text-ink'
            : 'font-normal text-ink-secondary hover:font-medium hover:text-ink',
    ].join(' ');
}

function mobileLinkClass({ isActive }) {
    return [
        'block rounded-lg px-3 py-3 text-body-lg no-underline transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
        isActive
            ? 'font-semibold text-ink bg-surface-raised'
            : 'font-normal text-ink-secondary hover:font-medium hover:text-ink hover:bg-surface',
    ].join(' ');
}

/**
 * TopNav — Etapa D §2.2.
 * Desktop: links inline. Mobile: hamburger + sheet leve.
 */
export default function TopNav() {
    const [menuOpen, setMenuOpen] = useState(false);
    const location = useLocation();
    const menuId = useId();

    useEffect(() => {
        setMenuOpen(false);
    }, [location.pathname]);

    useEffect(() => {
        if (!menuOpen) {
            return undefined;
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [menuOpen]);

    return (
        <header className="sticky top-0 z-20 border-b border-border-subtle bg-canvas/95 backdrop-blur-sm">
            <div className="flex h-14 items-center justify-between gap-4 px-6 md:h-16 md:px-8">
                <div className="flex min-w-0 items-center gap-4 md:gap-8">
                    <NavLink
                        to="/dashboard"
                        className="shrink-0 rounded-sm no-underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas"
                        aria-label="Aura — Visão geral"
                    >
                        <BrandMark size="sm" />
                    </NavLink>

                    <nav className="hidden items-center gap-6 sm:flex" aria-label="Principal">
                        {NAV_ITEMS.map((item) => (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                end={item.end}
                                className={navLinkClass}
                            >
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>
                </div>

                <div className="flex shrink-0 items-center gap-2 sm:gap-3">
                    <UserMenu />
                    <button
                        type="button"
                        className="inline-flex h-10 w-10 items-center justify-center rounded-full border border-border text-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas sm:hidden"
                        aria-label={menuOpen ? 'Fechar menu' : 'Abrir menu'}
                        aria-expanded={menuOpen}
                        aria-controls={menuId}
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        {menuOpen ? (
                            <X size={20} strokeWidth={1.75} aria-hidden />
                        ) : (
                            <Menu size={20} strokeWidth={1.75} aria-hidden />
                        )}
                    </button>
                </div>
            </div>

            {menuOpen ? (
                <div
                    id={menuId}
                    className="border-t border-border-subtle bg-canvas px-6 py-3 sm:hidden"
                >
                    <nav className="flex flex-col gap-1" aria-label="Principal mobile">
                        {NAV_ITEMS.map((item) => (
                            <NavLink
                                key={item.to}
                                to={item.to}
                                end={item.end}
                                className={mobileLinkClass}
                            >
                                {item.label}
                            </NavLink>
                        ))}
                    </nav>
                </div>
            ) : null}
        </header>
    );
}
