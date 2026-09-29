import { Outlet } from 'react-router-dom';
import TopNav from './TopNav';

/**
 * AppShell — layout autenticado (Etapa D §2.1 / §6.3).
 *
 * Full-bleed (sem moldura radius.2xl de “janela”).
 * `overflow-x-hidden` + `min-w-0` evitam scroll horizontal indesejado
 * (tabela usa overflow-x local).
 */
export default function AppShell() {
    return (
        <div className="flex min-h-dvh flex-col overflow-x-hidden bg-canvas font-sans text-ink">
            <TopNav />
            <main className="mx-auto w-full min-w-0 flex-1 px-6 py-6 md:px-8 md:py-8">
                <Outlet />
            </main>
        </div>
    );
}
