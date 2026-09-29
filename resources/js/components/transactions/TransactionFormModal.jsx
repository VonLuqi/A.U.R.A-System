import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import { isAdmin } from '../../lib/auth';
import { useAuth } from '../../hooks/useAuth';
import Button from '../ui/Button';
import CategoryFormSelect from '../ui/CategoryFormSelect';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

const EMPTY_ERRORS = {
    occurred_on: '',
    amount: '',
    type: '',
    description: '',
    category_id: '',
    notes: '',
};

/**
 * @param {string} raw
 * @returns {string}
 */
function normalizeAmountInput(raw) {
    return String(raw ?? '')
        .trim()
        .replace(/\s/g, '')
        .replace(',', '.');
}

/**
 * @param {string} raw
 * @returns {{ ok: true, value: string } | { ok: false, message: string }}
 */
function parseAmount(raw) {
    const normalized = normalizeAmountInput(raw);

    if (!normalized) {
        return { ok: false, message: 'Informe o valor.' };
    }

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) {
        return { ok: false, message: 'Use até duas casas decimais.' };
    }

    const value = Number(normalized);

    if (!(value > 0)) {
        return { ok: false, message: 'O valor deve ser maior que zero.' };
    }

    return { ok: true, value: value.toFixed(2) };
}

/**
 * TransactionFormModal — criar/editar (PLAN_EXPANSAO §8.2).
 *
 * @param {{
 *   open: boolean,
 *   mode: 'create'|'edit',
 *   transaction?: import('../../api/transactions').Transaction|null,
 *   categories?: Array<{ id: number, name: string, type?: string }>,
 *   categoriesLoading?: boolean,
 *   onCategoryCreated?: (category: { id: number, name: string }) => void,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function TransactionFormModal({
    open,
    mode = 'create',
    transaction = null,
    categories = [],
    categoriesLoading = false,
    onCategoryCreated,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const { user } = useAuth();
    const formId = useId();
    const descriptionRef = useRef(null);

    const [occurredOn, setOccurredOn] = useState('');
    const [amount, setAmount] = useState('');
    const [type, setType] = useState('debit');
    const [description, setDescription] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [notes, setNotes] = useState('');
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    const coreLocked = useMemo(() => {
        if (mode !== 'edit' || !transaction) {
            return false;
        }

        return transaction.source_kind === 'import' && !isAdmin(user);
    }, [mode, transaction, user]);

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');

        if (mode === 'edit' && transaction) {
            setOccurredOn(transaction.occurred_on ?? '');
            setAmount(String(transaction.amount ?? ''));
            setType(transaction.type === 'credit' ? 'credit' : 'debit');
            setDescription(transaction.description ?? '');
            setCategoryId(
                transaction.category?.id != null ? String(transaction.category.id) : '',
            );
            setNotes(typeof transaction.notes === 'string' ? transaction.notes : '');
            return;
        }

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');

        setOccurredOn(`${yyyy}-${mm}-${dd}`);
        setAmount('');
        setType('debit');
        setDescription('');
        setCategoryId('');
        setNotes('');
    }, [open, mode, transaction]);

    function clearField(name) {
        setFieldErrors((prev) => (prev[name] ? { ...prev, [name]: '' } : prev));
    }

    function validateClient() {
        const next = { ...EMPTY_ERRORS };

        if (!coreLocked) {
            if (!occurredOn) {
                next.occurred_on = 'Informe a data.';
            }

            const amountResult = parseAmount(amount);
            if (!amountResult.ok) {
                next.amount = amountResult.message;
            }

            if (type !== 'credit' && type !== 'debit') {
                next.type = 'O tipo deve ser credit ou debit.';
            }

            const trimmedDescription = description.trim();
            if (!trimmedDescription) {
                next.description = 'Informe a descrição.';
            } else if (trimmedDescription.length > 500) {
                next.description = 'A descrição deve ter no máximo 500 caracteres.';
            }
        }

        if (notes.length > 1000) {
            next.notes = 'As notas devem ter no máximo 1000 caracteres.';
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

        /** @type {Record<string, unknown>} */
        let payload;

        if (coreLocked) {
            payload = {
                category_id: categoryId === '' ? null : Number(categoryId),
                notes: notes.trim() === '' ? null : notes.trim(),
            };
        } else {
            const amountResult = parseAmount(amount);
            payload = {
                occurred_on: occurredOn,
                amount: amountResult.ok ? amountResult.value : amount,
                type,
                description: description.trim(),
                category_id: categoryId === '' ? null : Number(categoryId),
                notes: notes.trim() === '' ? null : notes.trim(),
            };
        }

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                setFieldErrors({
                    occurred_on: validation.occurred_on?.[0] ?? '',
                    amount: validation.amount?.[0] ?? '',
                    type: validation.type?.[0] ?? '',
                    description: validation.description?.[0] ?? '',
                    category_id: validation.category_id?.[0] ?? '',
                    notes: validation.notes?.[0] ?? '',
                });
                return;
            }

            const message = getErrorMessage(error);
            setFormError(message);
        }
    }

    const title = mode === 'edit' ? 'Editar lançamento' : 'Nova transação';
    const descriptionText =
        mode === 'edit'
            ? coreLocked
                ? 'Lançamento importado: você pode ajustar categoria e notas.'
                : 'Atualize os dados deste lançamento.'
            : 'Registre uma entrada ou saída manual.';

    return (
        <Modal
            open={open}
            title={title}
            description={descriptionText}
            onClose={onClose}
            closeOnScrim={!submitting}
            initialFocusRef={descriptionRef}
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
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field
                        label="Data"
                        htmlFor={`${formId}-date`}
                        error={fieldErrors.occurred_on}
                    >
                        <Input
                            id={`${formId}-date`}
                            type="date"
                            value={occurredOn}
                            disabled={submitting || coreLocked}
                            invalid={Boolean(fieldErrors.occurred_on)}
                            onChange={(event) => {
                                setOccurredOn(event.target.value);
                                clearField('occurred_on');
                            }}
                        />
                    </Field>

                    <Field
                        label="Valor"
                        htmlFor={`${formId}-amount`}
                        error={fieldErrors.amount}
                    >
                        <Input
                            id={`${formId}-amount`}
                            type="text"
                            inputMode="decimal"
                            placeholder="0,00"
                            value={amount}
                            disabled={submitting || coreLocked}
                            invalid={Boolean(fieldErrors.amount)}
                            onChange={(event) => {
                                setAmount(event.target.value);
                                clearField('amount');
                            }}
                        />
                    </Field>
                </div>

                <fieldset className="flex flex-col gap-2" disabled={submitting || coreLocked}>
                    <legend className="text-caption font-medium text-ink-secondary">Tipo</legend>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Tipo">
                        <TypeOption
                            active={type === 'debit'}
                            disabled={submitting || coreLocked}
                            onClick={() => {
                                setType('debit');
                                clearField('type');
                            }}
                        >
                            Saída
                        </TypeOption>
                        <TypeOption
                            active={type === 'credit'}
                            disabled={submitting || coreLocked}
                            onClick={() => {
                                setType('credit');
                                clearField('type');
                            }}
                        >
                            Entrada
                        </TypeOption>
                    </div>
                    {fieldErrors.type ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.type}
                        </p>
                    ) : null}
                </fieldset>

                <Field
                    label="Descrição"
                    htmlFor={`${formId}-description`}
                    error={fieldErrors.description}
                >
                    <Input
                        ref={descriptionRef}
                        id={`${formId}-description`}
                        type="text"
                        maxLength={500}
                        value={description}
                        disabled={submitting || coreLocked}
                        invalid={Boolean(fieldErrors.description)}
                        onChange={(event) => {
                            setDescription(event.target.value);
                            clearField('description');
                        }}
                    />
                </Field>

                <Field
                    label="Categoria"
                    htmlFor={`${formId}-category`}
                    error={fieldErrors.category_id}
                >
                    <CategoryFormSelect
                        id={`${formId}-category`}
                        categories={categories}
                        value={categoryId}
                        disabled={submitting || categoriesLoading}
                        invalid={Boolean(fieldErrors.category_id)}
                        onChange={(next) => {
                            setCategoryId(next);
                            clearField('category_id');
                        }}
                        onCreated={(category) => {
                            onCategoryCreated?.(category);
                        }}
                    />
                </Field>

                <Field label="Notas" htmlFor={`${formId}-notes`} error={fieldErrors.notes}>
                    <textarea
                        id={`${formId}-notes`}
                        rows={3}
                        maxLength={1000}
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
        <div className="flex flex-col gap-2 text-left">
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

function TypeOption({ active, disabled, onClick, children }) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            disabled={disabled}
            onClick={onClick}
            className={cx(
                'inline-flex h-10 items-center justify-center rounded-full px-4 text-caption font-medium transition',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                'disabled:cursor-not-allowed disabled:opacity-60',
                active
                    ? 'bg-brand text-ink-on-brand'
                    : 'border border-border bg-transparent text-ink hover:bg-surface-raised',
            )}
        >
            {children}
        </button>
    );
}
