import Button from '../ui/Button';

/**
 * Pagination — Etapa D §4.7.2.
 *
 * @param {{
 *   meta?: {
 *     current_page?: number,
 *     last_page?: number,
 *     total?: number,
 *     per_page?: number,
 *   }|null,
 *   onPageChange: (page: number) => void,
 *   className?: string,
 * }} props
 */
export default function Pagination({ meta = null, onPageChange, className = '' }) {
    if (!meta) {
        return null;
    }

    const currentPage = Math.max(1, Number(meta.current_page) || 1);
    const lastPage = Math.max(1, Number(meta.last_page) || 1);
    const total = Math.max(0, Number(meta.total) || 0);

    if (total === 0 && lastPage <= 1) {
        return (
            <p
                className={['text-caption text-ink-secondary', className].filter(Boolean).join(' ')}
            >
                0 movimentações
            </p>
        );
    }

    const canPrev = currentPage > 1;
    const canNext = currentPage < lastPage;

    return (
        <nav
            className={[
                'flex flex-wrap items-center justify-between gap-3',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            aria-label="Paginação das movimentações"
        >
            <p className="text-caption text-ink-secondary">
                Página {currentPage} de {lastPage}
                <span className="text-ink-muted"> · </span>
                {total} {total === 1 ? 'movimentação' : 'movimentações'}
            </p>

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={!canPrev}
                    onClick={() => onPageChange(currentPage - 1)}
                    aria-label="Página anterior"
                >
                    Anterior
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={!canNext}
                    onClick={() => onPageChange(currentPage + 1)}
                    aria-label="Próxima página"
                >
                    Próxima
                </Button>
            </div>
        </nav>
    );
}
