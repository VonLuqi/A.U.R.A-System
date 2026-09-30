import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { linkDebtorTransactions } from '../../api/debtors';
import { listTransactions } from '../../api/transactions';
import { useCategories } from '../../hooks/useCategories';
import { currentMonthRange, lastNDaysRange } from '../../lib/dates';
import { getErrorMessage } from '../../lib/errors';
import { formatDate, formatMoney } from '../../lib/format';
import { TOAST_DURATION } from '../../lib/toast';
import { cx } from '../../lib/cx';
import CategorySelect from '../dashboard/CategorySelect';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Modal from '../ui/Modal';
import Pill from '../ui/Pill';
import Skeleton from '../ui/Skeleton';

const PERIOD_OPTIONS = [
    { id: 'current_month', label: 'Este mês' },
    { id: 'last_30', label: '30 dias' },
    { id: 'last_90', label: '90 dias' },
];

/**
 * Modal para selecionar saídas e vincular a uma pessoa (devedor).
 *
 * @param {{
 *   open: boolean,
 *   debtor?: { id: number, name?: string }|null,
 *   onClose: () => void,
 *   onLinked?: () => void|Promise<void>,
 * }} props
 */
export default function LinkDebtorTransactionsModal({
    open,
    debtor = null,
    onClose,
    onLinked,
}) {
    const categories = useCategories();
    const [period, setPeriod] = useState('current_month');
    const [categoryId, setCategoryId] = useState(/** @type {''|number} */ (''));
    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [onlyUnlinked, setOnlyUnlinked] = useState(true);
    const [rows, setRows] = useState([]);
    const [status, setStatus] = useState('idle');
    const [error, setError] = useState(null);
    const [selected, setSelected] = useState(() => new Set());
    const [submitting, setSubmitting] = useState(false);

    const range = useMemo(() => {
        if (period === 'last_30') {
            return lastNDaysRange(30);
        }
        if (period === 'last_90') {
            return lastNDaysRange(90);
        }
        return currentMonthRange();
    }, [period]);

    useEffect(() => {
        const timer = window.setTimeout(() => setQ(qInput.trim()), 300);
        return () => window.clearTimeout(timer);
    }, [qInput]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        setSelected(new Set());
        setOnlyUnlinked(true);
        setPeriod('current_month');
        setCategoryId('');
        setQInput('');
        setQ('');
        setError(null);
    }, [open, debtor?.id]);

    useEffect(() => {
        if (!open || !debtor?.id) {
            return undefined;
        }

        let ignore = false;
        const controller = new AbortController();

        (async () => {
            setStatus('loading');
            setError(null);

            try {
                const params = {
                    from: range.from,
                    to: range.to,
                    type: 'debit',
                    per_page: 50,
                    sort: 'occurred_on',
                    direction: 'desc',
                    q: q || undefined,
                    category_id: categoryId === '' ? undefined : categoryId,
                    has_loan: onlyUnlinked ? 0 : undefined,
                };

                const result = await listTransactions(params, {
                    signal: controller.signal,
                });

                if (!ignore) {
                    setRows(result.data ?? []);
                    setStatus('success');
                }
            } catch (err) {
                if (ignore || err?.code === 'ERR_CANCELED' || err?.name === 'CanceledError') {
                    return;
                }
                setError(getErrorMessage(err));
                setStatus('error');
            }
        })();

        return () => {
            ignore = true;
            controller.abort();
        };
    }, [open, debtor?.id, range.from, range.to, q, onlyUnlinked, categoryId]);

    function toggleId(id) {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }

    function toggleAllVisible() {
        setSelected((prev) => {
            const visibleIds = rows.map((row) => row.id);
            const allSelected = visibleIds.length > 0
                && visibleIds.every((id) => prev.has(id));

            if (allSelected) {
                const next = new Set(prev);
                visibleIds.forEach((id) => next.delete(id));
                return next;
            }

            const next = new Set(prev);
            visibleIds.forEach((id) => next.add(id));
            return next;
        });
    }

    async function handleConfirm() {
        if (!debtor?.id || selected.size === 0 || submitting) {
            return;
        }

        setSubmitting(true);
        try {
            const result = await linkDebtorTransactions(
                debtor.id,
                Array.from(selected),
            );
            toast.success(result.message || 'Saídas vinculadas.', {
                duration: TOAST_DURATION,
            });
            await onLinked?.();
            onClose();
        } catch (err) {
            toast.error(getErrorMessage(err), { duration: TOAST_DURATION });
        } finally {
            setSubmitting(false);
        }
    }

    const selectedCount = selected.size;
    const allVisibleSelected =
        rows.length > 0 && rows.every((row) => selected.has(row.id));

    return (
        <Modal
            open={open}
            title="Vincular saídas"
            description={
                debtor?.name
                    ? `Selecione saídas para vincular a “${debtor.name}”.`
                    : 'Selecione saídas para vincular a esta pessoa.'
            }
            onClose={() => {
                if (!submitting) {
                    onClose();
                }
            }}
            closeOnScrim={!submitting}
            size="lg"
            bodyScroll={false}
            footer={(
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={submitting}
                        onClick={onClose}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        loading={submitting}
                        disabled={submitting || selectedCount === 0}
                        onClick={() => {
                            void handleConfirm();
                        }}
                    >
                        Vincular
                        {selectedCount > 0 ? ` (${selectedCount})` : ''}
                    </Button>
                </>
            )}
        >
            <div className="flex min-h-0 flex-1 flex-col gap-4">
                <div className="flex shrink-0 flex-wrap items-center gap-2" role="group" aria-label="Período">
                    {PERIOD_OPTIONS.map((option) => (
                        <Pill
                            key={option.id}
                            active={period === option.id}
                            className="min-h-8 px-2.5 py-1 text-small"
                            onClick={() => setPeriod(option.id)}
                        >
                            {option.label}
                        </Pill>
                    ))}
                </div>

                <div className="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center">
                    <CategorySelect
                        categories={categories.data ?? []}
                        value={categoryId}
                        loading={categories.status === 'loading'}
                        className="w-full sm:w-auto"
                        onChange={(next) => {
                            setCategoryId(next);
                            setSelected(new Set());
                        }}
                    />
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar na descrição"
                        aria-label="Buscar saídas"
                        className="min-w-0 flex-1"
                        disabled={submitting}
                        onChange={(event) => setQInput(event.target.value)}
                    />
                    <label className="inline-flex shrink-0 items-center gap-2 text-caption text-ink-secondary">
                        <input
                            type="checkbox"
                            className="size-4 rounded border-border"
                            checked={onlyUnlinked}
                            disabled={submitting}
                            onChange={(event) => setOnlyUnlinked(event.target.checked)}
                        />
                        Só sem pessoa
                    </label>
                </div>

                {status === 'error' ? (
                    <p className="shrink-0 text-caption text-feedback-danger" role="alert">
                        {error || 'Não foi possível carregar as saídas.'}
                    </p>
                ) : null}

                {status === 'loading' && rows.length === 0 ? (
                    <div className="flex flex-col gap-2" aria-busy="true">
                        {[0, 1, 2, 3].map((key) => (
                            <Skeleton key={key} className="h-12 w-full" radius="lg" />
                        ))}
                    </div>
                ) : null}

                {status !== 'loading' && rows.length === 0 && status !== 'error' ? (
                    <p className="py-4 text-center text-caption text-ink-muted">
                        Nenhuma saída encontrada neste filtro.
                    </p>
                ) : null}

                {rows.length > 0 ? (
                    <div className="flex min-h-0 flex-1 flex-col gap-2">
                        <div className="flex shrink-0 items-center justify-between gap-2">
                            <button
                                type="button"
                                className="text-caption font-medium text-ink-secondary transition hover:text-ink"
                                onClick={toggleAllVisible}
                            >
                                {allVisibleSelected ? 'Limpar seleção' : 'Selecionar visíveis'}
                            </button>
                            <span className="text-small text-ink-muted">
                                {rows.length} saída{rows.length === 1 ? '' : 's'}
                            </span>
                        </div>

                        <ul className="min-h-0 flex-1 divide-y divide-border-subtle overflow-y-auto rounded-xl border border-border-subtle">
                            {rows.map((row) => {
                                const checked = selected.has(row.id);
                                const alreadyLinked = Boolean(row.loan?.id);

                                return (
                                    <li key={row.id}>
                                        <label
                                            className={cx(
                                                'flex cursor-pointer items-start gap-3 px-3 py-2.5 transition',
                                                'hover:bg-surface-raised',
                                                checked && 'bg-brand/10',
                                            )}
                                        >
                                            <input
                                                type="checkbox"
                                                className="mt-1 size-4 shrink-0 rounded border-border"
                                                checked={checked}
                                                disabled={submitting}
                                                onChange={() => toggleId(row.id)}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p
                                                    className="truncate text-caption font-semibold text-ink"
                                                    title={row.description}
                                                >
                                                    {row.description || 'Sem descrição'}
                                                </p>
                                                <p className="mt-0.5 text-small text-ink-muted">
                                                    {formatDate(row.occurred_on)}
                                                    {row.category?.name
                                                        ? ` · ${row.category.name}`
                                                        : ''}
                                                    {alreadyLinked && row.loan?.debtor_name
                                                        ? ` · ${row.loan.debtor_name}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <span className="shrink-0 text-caption font-semibold tabular-nums text-feedback-danger">
                                                {formatMoney(row.amount)}
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ) : null}
            </div>
        </Modal>
    );
}
