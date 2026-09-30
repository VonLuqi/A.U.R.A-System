import { useEffect, useId, useState } from 'react';
import { formatMoney } from '../../lib/format';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import { parseLoanAmount } from '../../lib/loans';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

/**
 * MarkLoanPaidDialog — quitar / pagamento parcial (PLAN_CARTOES_EMPRESTIMOS §6.4).
 *
 * @param {{
 *   open: boolean,
 *   loan?: {
 *     debtor_name?: string,
 *     remaining_amount?: string|number,
 *     currency?: string,
 *   }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: (payload: { paid_amount?: string|null }) => void | Promise<void>,
 * }} props
 */
export default function MarkLoanPaidDialog({
    open,
    loan = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const formId = useId();
    const [paidAmount, setPaidAmount] = useState('');
    const [error, setError] = useState('');
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setPaidAmount('');
        setError('');
        setFormError('');
    }, [open, loan]);

    const remaining = loan?.remaining_amount != null
        ? formatMoney(loan.remaining_amount)
        : '—';
    const name = loan?.debtor_name?.trim() || 'esta cobrança';

    async function handleSubmit(event) {
        event.preventDefault();
        setError('');
        setFormError('');

        const trimmed = paidAmount.trim();
        /** @type {{ paid_amount?: string|null }} */
        const payload = {};

        if (trimmed) {
            const parsed = parseLoanAmount(trimmed);
            if (!parsed.ok) {
                setError(parsed.message);
                return;
            }
            payload.paid_amount = parsed.value;
        }

        try {
            await onConfirm(payload);
        } catch (err) {
            const validation = getValidationErrors(err);
            if (validation?.paid_amount?.[0]) {
                setError(validation.paid_amount[0]);
                return;
            }
            setFormError(getErrorMessage(err));
        }
    }

    return (
        <Modal
            open={open}
            title="Registrar pagamento"
            description={`Cobrança de ${name}. Restante: ${remaining}.`}
            onClose={onClose}
            closeOnScrim={!submitting}
            size="sm"
            footer={(
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={submitting}
                        onClick={onClose}
                    >
                        Voltar
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        size="sm"
                        loading={submitting}
                        disabled={submitting}
                    >
                        Confirmar
                    </Button>
                </>
            )}
        >
            <form id={formId} className="flex flex-col gap-3" onSubmit={handleSubmit} noValidate>
                <p className="text-body text-ink-secondary">
                    Deixe o valor em branco para quitar o restante. Informe um valor menor para
                    pagamento parcial.
                </p>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-paid`}>Valor pago (opcional)</Label>
                    <Input
                        id={`${formId}-paid`}
                        inputMode="decimal"
                        value={paidAmount}
                        disabled={submitting}
                        invalid={Boolean(error)}
                        placeholder={
                            loan?.remaining_amount != null
                                ? String(loan.remaining_amount)
                                : '0.00'
                        }
                        onChange={(event) => {
                            setPaidAmount(event.target.value);
                            setError('');
                        }}
                    />
                    {error ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {error}
                        </p>
                    ) : null}
                </div>
                {formError ? (
                    <p className="text-caption text-feedback-danger" role="alert">
                        {formError}
                    </p>
                ) : null}
            </form>
        </Modal>
    );
}
