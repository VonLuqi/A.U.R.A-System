import { TriangleAlert } from 'lucide-react';

/**
 * RowErrorsList — Etapa D §3.6.2.
 * Lista truncada de erros de linha do extrato (sem stack traces).
 *
 * @param {{
 *   errors?: Array<{ line?: number, message?: string }>,
 *   totalCount?: number,
 * }} props
 */
export default function RowErrorsList({ errors = [], totalCount }) {
    if (!errors.length) {
        return null;
    }

    const shown = errors.length;
    const total = typeof totalCount === 'number' && totalCount > 0 ? totalCount : shown;
    const truncated = total > shown;

    return (
        <div className="rounded-xl border border-border-subtle bg-surface-raised p-4">
            <div className="mb-3 flex items-start gap-2">
                <TriangleAlert
                    size={18}
                    strokeWidth={1.75}
                    className="mt-0.5 shrink-0 text-feedback-danger"
                    aria-hidden
                />
                <div className="min-w-0">
                    <p className="text-caption font-medium text-ink">Avisos nas linhas do extrato</p>
                    {truncated ? (
                        <p className="mt-1 text-caption text-ink-muted">
                            Mostrando {shown} de {total} erros.
                        </p>
                    ) : (
                        <p className="mt-1 text-caption text-ink-muted">
                            {shown} {shown === 1 ? 'erro' : 'erros'} encontrado{shown === 1 ? '' : 's'}.
                        </p>
                    )}
                </div>
            </div>

            <ul className="max-h-48 space-y-2 overflow-y-auto pr-1">
                {errors.map((item, index) => (
                    <li
                        key={`${item.line ?? 'x'}-${item.message ?? ''}-${index}`}
                        className="rounded-lg bg-surface-sunken px-3 py-2 text-caption text-ink"
                    >
                        <span className="font-medium text-ink-secondary">
                            Linha {item.line ?? '—'}:
                        </span>{' '}
                        <span>{item.message || 'Erro não especificado.'}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
