import { useCallback, useEffect, useMemo, useState } from 'react';
import { ChevronRight, Plus } from 'lucide-react';
import InstallmentPlanDetailModal from './InstallmentPlanDetailModal';
import InstallmentPlanFormModal from './InstallmentPlanFormModal';
import Button from '../ui/Button';
import EmptyState from '../ui/EmptyState';
import ErrorState from '../ui/ErrorState';
import Input from '../ui/Input';
import Skeleton from '../ui/Skeleton';
import {
    useCancelInstallmentPlan,
    useCreateInstallmentPlan,
    useMarkInstallmentItemOpen,
    useMarkInstallmentItemPaid,
    useUpdateInstallmentPlan,
} from '../../hooks/useInstallmentPlanMutations';
import { useInstallmentPlans } from '../../hooks/useInstallmentPlans';
import { getInstallmentPlan } from '../../api/installmentPlans';
import { cx } from '../../lib/cx';
import { formatMoney } from '../../lib/format';

/**
 * Lista + CRUD de parcelamentos (aba Movimentações).
 *
 * @param {{
 *   creditCards?: Array<{ id: number, name: string }>,
 *   debtors?: Array<{ id: number, name: string }>,
 *   openPlanId?: number|null,
 *   onOpenPlanConsumed?: () => void,
 * }} props
 */
export default function InstallmentPlansPanel({
    creditCards = [],
    debtors = [],
    openPlanId = null,
    onOpenPlanConsumed,
}) {
    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const [planFormOpen, setPlanFormOpen] = useState(false);
    const [selectedPlan, setSelectedPlan] = useState(null);

    const filters = useMemo(
        () => ({
            page,
            per_page: 50,
            q: q || undefined,
            backfill: 1,
        }),
        [page, q],
    );

    const plans = useInstallmentPlans(filters);

    const refresh = useCallback(async () => {
        await plans.refetch();
    }, [plans.refetch]);

    const createPlan = useCreateInstallmentPlan({
        onSuccess: async (plan) => {
            await refresh();
            setSelectedPlan(plan);
        },
    });
    const updatePlan = useUpdateInstallmentPlan({
        onSuccess: async (plan) => {
            setSelectedPlan(plan);
            await refresh();
        },
    });
    const cancelPlan = useCancelInstallmentPlan({
        onSuccess: async (plan) => {
            setSelectedPlan(plan);
            await refresh();
        },
    });
    const markItemPaid = useMarkInstallmentItemPaid({
        onSuccess: async (plan) => {
            setSelectedPlan(plan);
            await refresh();
        },
    });
    const markItemOpen = useMarkInstallmentItemOpen({
        onSuccess: async (plan) => {
            setSelectedPlan(plan);
            await refresh();
        },
    });

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQ(qInput.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [qInput]);

    useEffect(() => {
        if (!selectedPlan?.id) {
            return;
        }
        const fresh = plans.data.find((p) => p.id === selectedPlan.id);
        if (fresh) {
            setSelectedPlan(fresh);
        }
    }, [plans.data, selectedPlan?.id]);

    useEffect(() => {
        if (openPlanId == null) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const plan = await getInstallmentPlan(openPlanId);
                if (!cancelled) {
                    setSelectedPlan(plan);
                }
            } catch {
                // ignore — list still usable
            } finally {
                onOpenPlanConsumed?.();
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [openPlanId, onOpenPlanConsumed]);

    return (
        <>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Input
                    type="search"
                    value={qInput}
                    placeholder="Buscar por título"
                    aria-label="Buscar parcelamentos"
                    className="min-w-0 w-full sm:max-w-xs"
                    onChange={(event) => setQInput(event.target.value)}
                />
                <Button type="button" size="sm" onClick={() => setPlanFormOpen(true)}>
                    <Plus size={16} strokeWidth={2} aria-hidden />
                    Novo parcelamento
                </Button>
            </div>

            {plans.status === 'error' ? (
                <ErrorState
                    title="Não foi possível carregar os parcelamentos"
                    message={plans.error || 'Tente novamente em instantes.'}
                    onRetry={plans.refetch}
                />
            ) : null}

            {plans.status === 'loading' && plans.data.length === 0 ? (
                <div aria-busy="true" aria-label="Carregando parcelamentos">
                    <Skeleton.Table rows={5} />
                </div>
            ) : null}

            {plans.status !== 'error' &&
            plans.status !== 'loading' &&
            plans.data.length === 0 ? (
                <EmptyState
                    title="Nenhum parcelamento"
                    description="Importe uma fatura com Parcela X/Y ou cadastre manualmente."
                    action={{
                        label: 'Novo parcelamento',
                        onClick: () => setPlanFormOpen(true),
                    }}
                />
            ) : null}

            {plans.data.length > 0 ? (
                <div className="overflow-hidden rounded-xl border border-border-subtle bg-surface">
                    <ul
                        className={cx(
                            'divide-y divide-border-subtle',
                            plans.status === 'loading' && 'opacity-60',
                        )}
                    >
                        {plans.data.map((plan) => (
                            <li key={plan.id}>
                                <button
                                    type="button"
                                    className="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-surface-raised"
                                    onClick={() => setSelectedPlan(plan)}
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-body font-semibold text-ink">
                                            {plan.title}
                                        </p>
                                        <p className="mt-0.5 text-caption text-ink-secondary">
                                            {plan.paid_count}/{plan.total_count} pagas
                                            {plan.debtor?.name
                                                ? ` · ${plan.debtor.name}`
                                                : ''}
                                            {plan.credit_card?.name
                                                ? ` · ${plan.credit_card.name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <p className="shrink-0 text-body font-semibold tabular-nums text-ink">
                                        {Number(plan.open_remaining_total) > 0
                                            ? formatMoney(plan.open_remaining_total)
                                            : '—'}
                                    </p>
                                    <ChevronRight
                                        size={18}
                                        className="shrink-0 text-ink-muted"
                                        aria-hidden
                                    />
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {plans.meta && plans.meta.last_page > 1 ? (
                <div className="flex items-center justify-center gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={page <= 1 || plans.status === 'loading'}
                        onClick={() => setPage((p) => Math.max(1, p - 1))}
                    >
                        Anterior
                    </Button>
                    <span className="text-caption text-ink-secondary">
                        Página {plans.meta.current_page} de {plans.meta.last_page}
                    </span>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={
                            page >= plans.meta.last_page || plans.status === 'loading'
                        }
                        onClick={() => setPage((p) => p + 1)}
                    >
                        Próxima
                    </Button>
                </div>
            ) : null}

            <InstallmentPlanFormModal
                open={planFormOpen}
                creditCards={creditCards}
                debtors={debtors}
                submitting={createPlan.isLoading}
                onClose={() => {
                    if (!createPlan.isLoading) {
                        setPlanFormOpen(false);
                    }
                }}
                onSubmit={async (payload) => {
                    await createPlan.mutate(payload);
                    setPlanFormOpen(false);
                }}
            />

            <InstallmentPlanDetailModal
                open={selectedPlan !== null}
                plan={selectedPlan}
                debtors={debtors}
                submitting={
                    updatePlan.isLoading ||
                    cancelPlan.isLoading ||
                    markItemPaid.isLoading ||
                    markItemOpen.isLoading
                }
                onClose={() => setSelectedPlan(null)}
                onMarkPaid={async (number) => {
                    if (!selectedPlan?.id) {
                        return;
                    }
                    await markItemPaid.mutate(selectedPlan.id, number);
                }}
                onMarkOpen={async (number) => {
                    if (!selectedPlan?.id) {
                        return;
                    }
                    await markItemOpen.mutate(selectedPlan.id, number);
                }}
                onUpdate={async (payload) => {
                    if (!selectedPlan?.id) {
                        return;
                    }
                    await updatePlan.mutate(selectedPlan.id, payload);
                }}
                onCancelPlan={async () => {
                    if (!selectedPlan?.id) {
                        return;
                    }
                    await cancelPlan.mutate(selectedPlan.id);
                }}
            />
        </>
    );
}
