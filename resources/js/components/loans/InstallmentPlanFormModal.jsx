import { useEffect, useId, useMemo, useState } from 'react';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import { parseLoanAmount } from '../../lib/loans';
import Button from '../ui/Button';
import DateInput from '../ui/DateInput';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

function todayIso() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {{
 *   open: boolean,
 *   creditCards?: Array<{ id: number, name: string }>,
 *   debtors?: Array<{ id: number, name: string }>,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function InstallmentPlanFormModal({
    open,
    creditCards = [],
    debtors = [],
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const [title, setTitle] = useState('');
    const [totalCount, setTotalCount] = useState('10');
    const [amount, setAmount] = useState('');
    const [firstDueOn, setFirstDueOn] = useState(todayIso());
    const [creditCardId, setCreditCardId] = useState('');
    const [debtorId, setDebtorId] = useState('');
    const [notes, setNotes] = useState('');
    const [paidNumbers, setPaidNumbers] = useState(/** @type {number[]} */ ([]));
    const [errors, setErrors] = useState(/** @type {Record<string, string>} */ ({}));
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }
        setTitle('');
        setTotalCount('10');
        setAmount('');
        setFirstDueOn(todayIso());
        setCreditCardId('');
        setDebtorId('');
        setNotes('');
        setPaidNumbers([]);
        setErrors({});
        setFormError('');
    }, [open]);

    const total = Math.max(0, Number.parseInt(totalCount, 10) || 0);
    const numberOptions = useMemo(
        () => Array.from({ length: total }, (_, i) => i + 1),
        [total],
    );

    function togglePaid(n) {
        setPaidNumbers((prev) =>
            prev.includes(n) ? prev.filter((x) => x !== n) : [...prev, n].sort((a, b) => a - b),
        );
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setErrors({});
        setFormError('');

        const parsedAmount = parseLoanAmount(amount);
        const count = Number.parseInt(totalCount, 10);
        const nextErrors = {};

        if (!title.trim()) {
            nextErrors.title = 'Informe o título.';
        }
        if (!Number.isFinite(count) || count < 2) {
            nextErrors.total_count = 'Informe ao menos 2 parcelas.';
        }
        if (parsedAmount == null || parsedAmount <= 0) {
            nextErrors.installment_amount = 'Informe um valor válido.';
        }
        if (!firstDueOn) {
            nextErrors.first_due_on = 'Informe a data da 1ª parcela.';
        }

        if (Object.keys(nextErrors).length > 0) {
            setErrors(nextErrors);
            return;
        }

        try {
            await onSubmit({
                title: title.trim(),
                total_count: count,
                installment_amount: parsedAmount,
                first_due_on: firstDueOn,
                credit_card_id: creditCardId ? Number(creditCardId) : null,
                debtor_id: debtorId ? Number(debtorId) : null,
                notes: notes.trim() || null,
                paid_numbers: paidNumbers.filter((n) => n >= 1 && n <= count),
            });
        } catch (err) {
            const validation = getValidationErrors(err);
            if (validation && Object.keys(validation).length > 0) {
                const mapped = {};
                for (const [key, messages] of Object.entries(validation)) {
                    mapped[key] = Array.isArray(messages) ? messages[0] : String(messages);
                }
                setErrors(mapped);
            } else {
                setFormError(getErrorMessage(err));
            }
        }
    }

    return (
        <Modal
            open={open}
            onClose={submitting ? undefined : onClose}
            title="Novo parcelamento"
            size="lg"
            footer={(
                <>
                    <Button type="button" variant="secondary" disabled={submitting} onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={formId} disabled={submitting}>
                        {submitting ? 'Salvando…' : 'Criar'}
                    </Button>
                </>
            )}
        >
            <form id={formId} className="flex flex-col gap-4" onSubmit={handleSubmit}>
                {formError ? (
                    <p className="text-caption text-danger" role="alert">
                        {formError}
                    </p>
                ) : null}

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-title`}>Título</Label>
                    <Input
                        id={`${formId}-title`}
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder="Ex.: Magazine Luiza"
                        disabled={submitting}
                    />
                    {errors.title ? (
                        <p className="text-caption text-danger">{errors.title}</p>
                    ) : null}
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${formId}-count`}>Parcelas</Label>
                        <Input
                            id={`${formId}-count`}
                            type="number"
                            min={2}
                            max={120}
                            value={totalCount}
                            onChange={(e) => setTotalCount(e.target.value)}
                            disabled={submitting}
                        />
                        {errors.total_count ? (
                            <p className="text-caption text-danger">{errors.total_count}</p>
                        ) : null}
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${formId}-amount`}>Valor de cada parcela</Label>
                        <Input
                            id={`${formId}-amount`}
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            placeholder="0,00"
                            disabled={submitting}
                        />
                        {errors.installment_amount ? (
                            <p className="text-caption text-danger">{errors.installment_amount}</p>
                        ) : null}
                    </div>
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-first`}>Data da 1ª parcela</Label>
                    <DateInput
                        id={`${formId}-first`}
                        value={firstDueOn}
                        onChange={(e) => setFirstDueOn(e.target.value)}
                        disabled={submitting}
                    />
                    {errors.first_due_on ? (
                        <p className="text-caption text-danger">{errors.first_due_on}</p>
                    ) : null}
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${formId}-card`}>Cartão (opcional)</Label>
                        <select
                            id={`${formId}-card`}
                            className="h-11 rounded-xl border border-border bg-surface px-3 text-body text-ink"
                            value={creditCardId}
                            onChange={(e) => setCreditCardId(e.target.value)}
                            disabled={submitting}
                        >
                            <option value="">Nenhum</option>
                            {creditCards.map((card) => (
                                <option key={card.id} value={card.id}>
                                    {card.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${formId}-debtor`}>Pessoa (opcional)</Label>
                        <select
                            id={`${formId}-debtor`}
                            className="h-11 rounded-xl border border-border bg-surface px-3 text-body text-ink"
                            value={debtorId}
                            onChange={(e) => setDebtorId(e.target.value)}
                            disabled={submitting}
                        >
                            <option value="">Nenhuma</option>
                            {debtors.map((d) => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {numberOptions.length > 0 ? (
                    <fieldset className="flex flex-col gap-2">
                        <legend className="text-caption font-medium text-ink-secondary">
                            Já pagas
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {numberOptions.map((n) => {
                                const active = paidNumbers.includes(n);
                                return (
                                    <button
                                        key={n}
                                        type="button"
                                        className={
                                            active
                                                ? 'rounded-full border border-brand bg-brand/15 px-3 py-1 text-caption font-medium text-ink'
                                                : 'rounded-full border border-border px-3 py-1 text-caption text-ink-secondary hover:bg-surface-raised'
                                        }
                                        onClick={() => togglePaid(n)}
                                        disabled={submitting}
                                        aria-pressed={active}
                                    >
                                        {n}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                ) : null}

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-notes`}>Notas</Label>
                    <Input
                        id={`${formId}-notes`}
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        disabled={submitting}
                    />
                </div>
            </form>
        </Modal>
    );
}
