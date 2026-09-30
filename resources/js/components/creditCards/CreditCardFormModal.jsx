import { useEffect, useId, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import {
    parseDayOfMonth,
    parseLastFour,
    parseOptionalAmount,
} from '../../lib/creditCards';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

const EMPTY_ERRORS = {
    name: '',
    limit_amount: '',
    closing_day: '',
    due_day: '',
    last_four: '',
    notes: '',
    is_active: '',
    is_default: '',
};

/**
 * CreditCardFormModal — criar/editar cartão (PLAN_CARTOES_EMPRESTIMOS §6.3).
 *
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   card?: import('../../api/creditCards').CreditCard|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function CreditCardFormModal({
    open,
    mode = 'create',
    card = null,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const nameRef = useRef(null);

    const [name, setName] = useState('');
    const [limitAmount, setLimitAmount] = useState('');
    const [closingDay, setClosingDay] = useState('');
    const [dueDay, setDueDay] = useState('');
    const [lastFour, setLastFour] = useState('');
    const [notes, setNotes] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [isDefault, setIsDefault] = useState(false);
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');

        if (mode === 'edit' && card) {
            setName(card.name ?? '');
            setLimitAmount(card.limit_amount != null ? String(card.limit_amount) : '');
            setClosingDay(card.closing_day != null ? String(card.closing_day) : '');
            setDueDay(card.due_day != null ? String(card.due_day) : '');
            setLastFour(card.last_four != null ? String(card.last_four) : '');
            setNotes(card.notes ?? '');
            setIsActive(card.is_active !== false);
            setIsDefault(card.is_default === true);
            return;
        }

        setName('');
        setLimitAmount('');
        setClosingDay('');
        setDueDay('');
        setLastFour('');
        setNotes('');
        setIsActive(true);
        setIsDefault(false);
    }, [open, mode, card]);

    function clearField(key) {
        setFieldErrors((prev) => (prev[key] ? { ...prev, [key]: '' } : prev));
    }

    function validateClient() {
        const next = { ...EMPTY_ERRORS };
        const trimmedName = name.trim();

        if (!trimmedName) {
            next.name = 'Informe o nome do cartão.';
        } else if (trimmedName.length > 120) {
            next.name = 'O nome deve ter no máximo 120 caracteres.';
        }

        const limit = parseOptionalAmount(limitAmount);
        if (!limit.ok) {
            next.limit_amount = limit.message;
        }

        const closing = parseDayOfMonth(closingDay);
        if (!closing.ok) {
            next.closing_day = closing.message;
        }

        const due = parseDayOfMonth(dueDay);
        if (!due.ok) {
            next.due_day = due.message;
        }

        const four = parseLastFour(lastFour);
        if (!four.ok) {
            next.last_four = four.message;
        }

        if (notes.length > 2000) {
            next.notes = 'As notas devem ter no máximo 2000 caracteres.';
        }

        setFieldErrors(next);

        return !Object.values(next).some(Boolean);
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setFormError('');

        if (!validateClient()) {
            return;
        }

        const limit = parseOptionalAmount(limitAmount);
        const closing = parseDayOfMonth(closingDay);
        const due = parseDayOfMonth(dueDay);
        const four = parseLastFour(lastFour);

        /** @type {Record<string, unknown>} */
        const payload = {
            name: name.trim(),
            limit_amount: limit.ok ? limit.value : null,
            closing_day: closing.ok ? closing.value : Number(closingDay),
            due_day: due.ok ? due.value : Number(dueDay),
            last_four: four.ok ? four.value : null,
            notes: notes.trim() ? notes.trim() : null,
            is_active: isActive,
            is_default: isDefault,
        };

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                setFieldErrors({
                    name: validation.name?.[0] ?? '',
                    limit_amount: validation.limit_amount?.[0] ?? '',
                    closing_day: validation.closing_day?.[0] ?? '',
                    due_day: validation.due_day?.[0] ?? '',
                    last_four: validation.last_four?.[0] ?? '',
                    notes: validation.notes?.[0] ?? '',
                    is_active: validation.is_active?.[0] ?? '',
                    is_default: validation.is_default?.[0] ?? '',
                });

                if (validation.credit_card?.[0]) {
                    setFormError(validation.credit_card[0]);
                }

                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    const title = mode === 'edit' ? 'Editar cartão' : 'Novo cartão';

    return (
        <Modal
            open={open}
            title={title}
            description="Cadastre limite, fechamento e dia de vencimento da fatura."
            onClose={onClose}
            closeOnScrim={!submitting}
            initialFocusRef={nameRef}
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
                        type="submit"
                        form={formId}
                        size="sm"
                        loading={submitting}
                        disabled={submitting}
                    >
                        {mode === 'edit' ? 'Salvar' : 'Criar'}
                    </Button>
                </>
            )}
        >
            <form id={formId} className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
                <Field label="Nome" htmlFor={`${formId}-name`} error={fieldErrors.name}>
                    <Input
                        ref={nameRef}
                        id={`${formId}-name`}
                        value={name}
                        maxLength={120}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.name)}
                        placeholder="Ex.: Nubank"
                        onChange={(event) => {
                            setName(event.target.value);
                            clearField('name');
                        }}
                    />
                </Field>

                <Field
                    label="Limite (opcional)"
                    htmlFor={`${formId}-limit`}
                    error={fieldErrors.limit_amount}
                >
                    <Input
                        id={`${formId}-limit`}
                        inputMode="decimal"
                        value={limitAmount}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.limit_amount)}
                        placeholder="0.00"
                        onChange={(event) => {
                            setLimitAmount(event.target.value);
                            clearField('limit_amount');
                        }}
                    />
                </Field>

                <div className="grid grid-cols-2 gap-3">
                    <Field
                        label="Fechamento"
                        htmlFor={`${formId}-closing`}
                        error={fieldErrors.closing_day}
                    >
                        <Input
                            id={`${formId}-closing`}
                            inputMode="numeric"
                            value={closingDay}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.closing_day)}
                            placeholder="1–31"
                            onChange={(event) => {
                                setClosingDay(event.target.value);
                                clearField('closing_day');
                            }}
                        />
                    </Field>
                    <Field
                        label="Vencimento"
                        htmlFor={`${formId}-due`}
                        error={fieldErrors.due_day}
                    >
                        <Input
                            id={`${formId}-due`}
                            inputMode="numeric"
                            value={dueDay}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.due_day)}
                            placeholder="1–31"
                            onChange={(event) => {
                                setDueDay(event.target.value);
                                clearField('due_day');
                            }}
                        />
                    </Field>
                </div>

                <Field
                    label="Últimos 4 dígitos (opcional)"
                    htmlFor={`${formId}-last-four`}
                    error={fieldErrors.last_four}
                >
                    <Input
                        id={`${formId}-last-four`}
                        inputMode="numeric"
                        maxLength={4}
                        value={lastFour}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.last_four)}
                        placeholder="1234"
                        onChange={(event) => {
                            setLastFour(event.target.value.replace(/\D/g, '').slice(0, 4));
                            clearField('last_four');
                        }}
                    />
                </Field>

                <Field label="Notas" htmlFor={`${formId}-notes`} error={fieldErrors.notes}>
                    <textarea
                        id={`${formId}-notes`}
                        rows={3}
                        maxLength={2000}
                        value={notes}
                        disabled={submitting}
                        aria-invalid={Boolean(fieldErrors.notes) || undefined}
                        className={cx(
                            'w-full resize-y rounded-lg border bg-surface-sunken px-3 py-2.5 font-sans text-body text-ink',
                            'placeholder:text-ink-muted',
                            'outline-none transition-[border-color,box-shadow]',
                            'focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                            fieldErrors.notes ? 'border-feedback-danger' : 'border-border',
                        )}
                        placeholder="Opcional"
                        onChange={(event) => {
                            setNotes(event.target.value);
                            clearField('notes');
                        }}
                    />
                </Field>

                <label className="flex items-center gap-2 text-body text-ink">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded border-border text-brand focus-visible:ring-brand"
                        checked={isActive}
                        disabled={submitting}
                        onChange={(event) => {
                            const next = event.target.checked;
                            setIsActive(next);
                            if (!next) {
                                setIsDefault(false);
                            }
                            clearField('is_active');
                            clearField('is_default');
                        }}
                    />
                    Cartão ativo
                </label>

                <label className="flex items-center gap-2 text-body text-ink">
                    <input
                        type="checkbox"
                        className="h-4 w-4 rounded border-border text-brand focus-visible:ring-brand"
                        checked={isDefault}
                        disabled={submitting || !isActive}
                        onChange={(event) => {
                            setIsDefault(event.target.checked);
                            clearField('is_default');
                        }}
                    />
                    Padrão para importar faturas
                </label>

                {fieldErrors.is_default ? (
                    <p className="text-caption text-feedback-danger" role="alert">
                        {fieldErrors.is_default}
                    </p>
                ) : null}

                {formError ? (
                    <p className="text-caption text-feedback-danger" role="alert">
                        {formError}
                    </p>
                ) : null}
            </form>
        </Modal>
    );
}

function Field({ label, htmlFor, error, children }) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error ? (
                <p className="text-caption text-feedback-danger" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
