import { Link } from 'react-router-dom';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

/**
 * NotFoundPage — catch-all client (Etapa D §0.3 / §5.5).
 */
export default function NotFoundPage() {
    useDocumentTitle('Página não encontrada · Aura');

    return (
        <main className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-canvas px-6 font-sans text-ink">
            <h1 className="text-h1 font-bold">Página não encontrada</h1>
            <p className="text-body text-ink-secondary">
                <Link
                    to="/dashboard"
                    className="text-ink underline decoration-border underline-offset-4 transition hover:decoration-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                >
                    Voltar ao dashboard
                </Link>
            </p>
        </main>
    );
}
