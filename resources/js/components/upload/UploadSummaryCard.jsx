import { Link } from 'react-router-dom';
import Badge from '../ui/Badge';
import Button from '../ui/Button';
import Card from '../ui/Card';

/**
 * UploadSummaryCard — Etapa D §3.6.1 / §5.6.
 * Sucesso / sucesso parcial (skips e avisos de linha).
 * Whitelist de campos públicos — nunca renderiza `stored_path` / `checksum`.
 *
 * @param {{
 *   summary: Record<string, unknown>,
 *   onReset?: () => void,
 * }} props
 */
export default function UploadSummaryCard({ summary, onReset }) {
    if (!summary) {
        return null;
    }

    // Campos sensíveis do backend não entram na UI (§5.6).
    const {
        stored_path: _storedPath,
        checksum: _checksum,
        file_checksum: _fileChecksum,
        ...publicSummary
    } = summary;
    void _storedPath;
    void _checksum;
    void _fileChecksum;

    const rowsSkipped = Number(publicSummary.rows_skipped ?? 0);
    const rowErrorsCount = Number(publicSummary.row_errors_count ?? 0);
    const hasWarnings = rowsSkipped > 0 || rowErrorsCount > 0;
    const formatLabel = String(publicSummary.format ?? '').toUpperCase() || '—';

    const metrics = [
        { label: 'Total', value: publicSummary.rows_total ?? 0 },
        { label: 'Importadas', value: publicSummary.rows_imported ?? 0 },
        { label: 'Ignoradas', value: rowsSkipped },
        { label: 'Erros de linha', value: rowErrorsCount },
    ];

    return (
        <Card className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
                <h2 className="text-h3 font-semibold text-ink">
                    {hasWarnings ? 'Importação concluída com avisos' : 'Importação concluída'}
                </h2>
                <div className="flex flex-wrap items-center gap-2">
                    <p
                        className="truncate text-body text-ink-secondary"
                        title={String(publicSummary.original_filename ?? '')}
                    >
                        {publicSummary.original_filename || 'Extrato importado'}
                    </p>
                    <Badge tone="brand">{formatLabel}</Badge>
                </div>
            </div>

            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {metrics.map((metric) => (
                    <div
                        key={metric.label}
                        className="rounded-lg bg-surface-sunken px-3 py-3"
                    >
                        <dt className="text-caption text-ink-muted">{metric.label}</dt>
                        <dd className="mt-1 text-h3 font-semibold text-ink">{metric.value}</dd>
                    </div>
                ))}
            </dl>

            {rowsSkipped > 0 ? (
                <p className="text-caption text-ink-secondary">
                    Movimentações já existentes foram ignoradas.
                </p>
            ) : null}

            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <Link
                    to="/dashboard"
                    className="inline-flex h-11 items-center justify-center rounded-full bg-brand px-5 text-caption font-semibold text-ink-on-brand no-underline transition hover:brightness-95"
                >
                    Ir ao dashboard
                </Link>
                {onReset ? (
                    <Button type="button" variant="secondary" onClick={onReset}>
                        Enviar outro arquivo
                    </Button>
                ) : null}
            </div>
        </Card>
    );
}
