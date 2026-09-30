import { useEffect, useState } from 'react';
import { getErrorMessage } from '../../lib/errors';
import { formatDate, formatMoney } from '../../lib/format';
import Badge from '../ui/Badge';
import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * @param {{
 *   open: boolean,
 *   plan: import('../../api/installmentPlans').InstallmentPlan|null,
 *   debtors?: Array<{ id: number, name: string }>,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onMarkPaid: (number: number) => Promise<void>,
 *   onMarkOpen: (number: number) => Promise<void>,
 *   onUpdate: (payload: object) => Promise<void>,
 *   onCancelPlan: () => Promise<void>,
 * }} props
 */
export default function InstallmentPlanDetailModal({
    open,
    plan,
    debtors = [],
    submitting = false,
    onClose,
    onMarkPaid,
    onMarkOpen,
    onUpdate,
    onCancelPlan,
}) {
    const [debtorId, setDebtorId] = useState('');
    const [actionError, setActionError] = useState('');
    const [busyNumber, setBusyNumber] = useState(null);

    useEffect(() => {
        if (!open || !plan) {
            return;
        }
        setDebtorId(plan.debtor_id != null ? String(plan.debtor_id) : '');
        setActionError('');
        setBusyNumber(null);
    }, [open, plan?.id, plan?.debtor_id]);

    if (!plan) {
        return null;
    }

    const items = Array.isArray(plan.items) ? plan.items : [];
    const canAct = plan.status !== 'cancelled';

    async function handleDebtorChange(value) {
        setDebtorId(value);
        setActionError('');
        try {
            await onUpdate({
                debtor_id: value ? Number(value) : null,
            });
        } catch (err) {
            setActionError(getErrorMessage(err));
        }
    }

    async function handleMarkPaid(number) {
        setBusyNumber(number);
        setActionError('');
        try {
            await onMarkPaid(number);
        } catch (err) {
            setActionError(getErrorMessage(err));
        } finally {
            setBusyNumber(null);
        }
    }

    async function handleMarkOpen(number) {
        setBusyNumber(number);
        setActionError('');
        try {
            await onMarkOpen(number);
        } catch (err) {
            setActionError(getErrorMessage(err));
        } finally {
            setBusyNumber(null);
        }
    }

    async function handleCancel() {
        if (!window.confirm('Cancelar este parcelamento e as parcelas em aberto?')) {
            return;
        }
        setActionError('');
        try {
            await onCancelPlan();
        } catch (err) {
            setActionError(getErrorMessage(err));
        }
    }

    return (
        <Modal
            open={open}
            onClose={submitting ? undefined : onClose}
            title={plan.title}
            size="lg"
            footer={(
                <>
                    {canAct ? (
                        <Button
                            type="button"
                            variant="danger"
                            disabled={submitting}
                            onClick={handleCancel}
                        >
                            Cancelar plano
                        </Button>
                    ) : null}
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Fechar
                    </Button>
                </>
            )}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2 text-caption text-ink-secondary">
                    <Badge tone={plan.status === 'paid' ? 'positive' : plan.status === 'cancelled' ? 'meta' : 'danger'}>
                        {plan.paid_count}/{plan.total_count} pagas
                    </Badge>
                    <span>Restante {formatMoney(plan.open_remaining_total)}</span>
                    {plan.credit_card?.name ? <span>· {plan.credit_card.name}</span> : null}
                </div>

                <div className="flex flex-col gap-1.5">
                    <label className="text-caption font-medium text-ink-secondary" htmlFor="plan-debtor">
                        Pessoa (cobrança)
                    </label>
                    <select
                        id="plan-debtor"
                        className="h-11 rounded-xl border border-border bg-surface px-3 text-body text-ink"
                        value={debtorId}
                        disabled={submitting || !canAct}
                        onChange={(e) => handleDebtorChange(e.target.value)}
                    >
                        <option value="">Nenhuma</option>
                        {debtors.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.name}
                            </option>
                        ))}
                    </select>
                </div>

                {actionError ? (
                    <p className="text-caption text-danger" role="alert">
                        {actionError}
                    </p>
                ) : null}

                <ul className="divide-y divide-border-subtle overflow-hidden rounded-xl border border-border-subtle">
                    {items.map((item) => {
                        const busy = busyNumber === item.number || submitting;
                        return (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-3 py-3"
                            >
                                <div className="min-w-0">
                                    <p className="text-body font-medium text-ink">
                                        Parcela {item.number}/{plan.total_count}
                                    </p>
                                    <p className="text-caption text-ink-secondary">
                                        {formatMoney(item.amount)}
                                        {item.due_on ? ` · ${formatDate(item.due_on)}` : ''}
                                        {item.transaction_id ? ' · saída vinculada' : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        tone={
                                            item.status === 'paid'
                                                ? 'positive'
                                                : item.status === 'cancelled'
                                                  ? 'meta'
                                                  : 'danger'
                                        }
                                    >
                                        {item.status === 'paid'
                                            ? 'Paga'
                                            : item.status === 'cancelled'
                                              ? 'Cancelada'
                                              : 'Em aberto'}
                                    </Badge>
                                    {canAct && item.status === 'open' ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            disabled={busy}
                                            onClick={() => handleMarkPaid(item.number)}
                                        >
                                            Pagar
                                        </Button>
                                    ) : null}
                                    {canAct && item.status === 'paid' ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="secondary"
                                            disabled={busy}
                                            onClick={() => handleMarkOpen(item.number)}
                                        >
                                            Reabrir
                                        </Button>
                                    ) : null}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </Modal>
    );
}
