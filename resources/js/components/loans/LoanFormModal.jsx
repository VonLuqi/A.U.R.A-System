import { useEffect, useId, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import {
    LOAN_KINDS,
    LOAN_KIND_LABELS,
    parseLoanAmount,
} from '../../lib/loans';
import Button from '../ui/Button';
import CategoryFormSelect from '../ui/CategoryFormSelect';
import DateInput from '../ui/DateInput';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

const EMPTY_ERRORS = {
    debtor_name: '',
    kind: '',
    credit_card_id: '',
    amount: '',
    lent_on: '',
    due_on: '',
    notes: '',
    create_expense: '',
    expense_description: '',
    expense_amount: '',
    expense_occurred_on: '',
    expense_category_id: '',
};

function todayIso() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * LoanFormModal — criar/editar cobrança (PLAN_CARTOES_EMPRESTIMOS §6.4).
 *
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   loan?: import('../../api/loans').Loan|null,
 *   creditCards?: Array<{ id: number, name: string, is_active?: boolean }>,
 *   creditCardsLoading?: boolean,
 *   debtors?: Array<{ id: number, name: string }>,
 *   debtorsLoading?: boolean,
 *   onCreateDebtor?: () => void,
 *   categories?: Array<{ id: number, name: string }>,
 *   categoriesLoading?: boolean,
 *   onCategoryCreated?: (category: { id: number, name: string }) => void,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function LoanFormModal({
    open,
    mode = 'create',
    loan = null,
    creditCards = [],
    creditCardsLoading = false,
    debtors = [],
    debtorsLoading = false,
    onCreateDebtor,
    categories = [],
    categoriesLoading = false,
    onCategoryCreated,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const nameRef = useRef(null);

    const [debtorId, setDebtorId] = useState('');
    const [debtorName, setDebtorName] = useState('');
    const [kind, setKind] = useState(LOAN_KINDS.cash);
    const [creditCardId, setCreditCardId] = useState('');
    const [amount, setAmount] = useState('');
    const [lentOn, setLentOn] = useState('');
    const [dueOn, setDueOn] = useState('');
    const [notes, setNotes] = useState('');
    const [createExpense, setCreateExpense] = useState(true);
    const [expenseDescription, setExpenseDescription] = useState('');
    const [expenseAmount, setExpenseAmount] = useState('');
    const [expenseOccurredOn, setExpenseOccurredOn] = useState('');
    const [expenseCategoryId, setExpenseCategoryId] = useState('');
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');

        if (mode === 'edit' && loan) {
            setDebtorId(loan.debtor_id != null ? String(loan.debtor_id) : '');
            setDebtorName(loan.debtor_name ?? '');
            setKind(
                loan.kind === LOAN_KINDS.card_limit
                    ? LOAN_KINDS.card_limit
                    : LOAN_KINDS.cash,
            );
            setCreditCardId(
                loan.credit_card_id != null ? String(loan.credit_card_id) : '',
            );
            setAmount(loan.amount != null ? String(loan.amount) : '');
            setLentOn(loan.lent_on ?? '');
            setDueOn(loan.due_on ?? '');
            setNotes(loan.notes ?? '');
            setCreateExpense(false);
            setExpenseDescription('');
            setExpenseAmount('');
            setExpenseOccurredOn('');
            setExpenseCategoryId('');
            return;
        }

        const today = todayIso();
        setDebtorId('');
        setDebtorName('');
        setKind(LOAN_KINDS.cash);
        setCreditCardId('');
        setAmount('');
        setLentOn(today);
        setDueOn(today);
        setNotes('');
        setCreateExpense(true);
        setExpenseDescription('');
        setExpenseAmount('');
        setExpenseOccurredOn(today);
        setExpenseCategoryId('');
    }, [open, mode, loan]);

    function clearField(key) {
        setFieldErrors((prev) => (prev[key] ? { ...prev, [key]: '' } : prev));
    }

    function validateClient() {
        const next = { ...EMPTY_ERRORS };
        const trimmed = debtorName.trim();
        const hasDebtorId = Boolean(debtorId);

        if (!hasDebtorId && !trimmed) {
            next.debtor_name = 'Selecione ou cadastre a pessoa.';
        } else if (!hasDebtorId && trimmed.length > 160) {
            next.debtor_name = 'O nome deve ter no máximo 160 caracteres.';
        }

        if (kind !== LOAN_KINDS.cash && kind !== LOAN_KINDS.card_limit) {
            next.kind = 'Tipo inválido.';
        }

        if (kind === LOAN_KINDS.card_limit && !creditCardId) {
            next.credit_card_id = 'Informe o cartão quando o tipo for limite do cartão.';
        }

        const parsedAmount = parseLoanAmount(amount);
        if (!parsedAmount.ok) {
            next.amount = parsedAmount.message;
        }

        if (!lentOn) {
            next.lent_on = 'Informe a data do empréstimo.';
        }

        if (!dueOn) {
            next.due_on = 'Informe a data de cobrança.';
        } else if (lentOn && dueOn < lentOn) {
            next.due_on = 'A data de cobrança deve ser igual ou posterior à data do empréstimo.';
        }

        if (notes.length > 2000) {
            next.notes = 'As notas devem ter no máximo 2000 caracteres.';
        }

        if (mode === 'create' && createExpense) {
            if (!expenseDescription.trim()) {
                next.expense_description = 'Informe o que foi comprado / a descrição da saída.';
            } else if (expenseDescription.trim().length > 500) {
                next.expense_description = 'A descrição deve ter no máximo 500 caracteres.';
            }

            if (expenseAmount.trim()) {
                const parsedExpense = parseLoanAmount(expenseAmount);
                if (!parsedExpense.ok) {
                    next.expense_amount = parsedExpense.message;
                }
            }
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

        const parsedAmount = parseLoanAmount(amount);

        /** @type {Record<string, unknown>} */
        const payload = {
            kind,
            amount: parsedAmount.ok ? parsedAmount.value : amount,
            lent_on: lentOn,
            due_on: dueOn,
            notes: notes.trim() ? notes.trim() : null,
            credit_card_id:
                kind === LOAN_KINDS.card_limit && creditCardId
                    ? Number(creditCardId)
                    : null,
        };

        if (debtorId) {
            payload.debtor_id = Number(debtorId);
        } else {
            payload.debtor_name = debtorName.trim();
        }

        if (mode === 'create') {
            payload.create_expense = createExpense;
            if (createExpense) {
                payload.expense_description = expenseDescription.trim();
                const parsedExpense = expenseAmount.trim()
                    ? parseLoanAmount(expenseAmount)
                    : null;
                if (parsedExpense?.ok) {
                    payload.expense_amount = parsedExpense.value;
                }
                payload.expense_occurred_on = expenseOccurredOn || lentOn;
                payload.expense_category_id = expenseCategoryId
                    ? Number(expenseCategoryId)
                    : null;
            }
        }

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                setFieldErrors({
                    debtor_name: validation.debtor_name?.[0] ?? '',
                    kind: validation.kind?.[0] ?? '',
                    credit_card_id: validation.credit_card_id?.[0] ?? '',
                    amount: validation.amount?.[0] ?? '',
                    lent_on: validation.lent_on?.[0] ?? '',
                    due_on: validation.due_on?.[0] ?? '',
                    notes: validation.notes?.[0] ?? '',
                    create_expense: validation.create_expense?.[0] ?? '',
                    expense_description: validation.expense_description?.[0] ?? '',
                    expense_amount: validation.expense_amount?.[0] ?? '',
                    expense_occurred_on: validation.expense_occurred_on?.[0] ?? '',
                    expense_category_id: validation.expense_category_id?.[0] ?? '',
                });
                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    const title = mode === 'edit' ? 'Editar cobrança' : 'Nova cobrança';
    const cardOptions = creditCards.filter(
        (card) => card.is_active !== false || String(card.id) === creditCardId,
    );

    return (
        <Modal
            open={open}
            title={title}
            description="Registre valores a cobrar de terceiros (dinheiro ou limite do cartão)."
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
                <Field
                    label="Pessoa"
                    htmlFor={`${formId}-debtor`}
                    error={fieldErrors.debtor_name}
                >
                    <div className="flex flex-col gap-2">
                        <select
                            id={`${formId}-debtor`}
                            ref={nameRef}
                            value={debtorId}
                            disabled={submitting || debtorsLoading}
                            aria-invalid={Boolean(fieldErrors.debtor_name) || undefined}
                            className={selectClass(Boolean(fieldErrors.debtor_name))}
                            onChange={(event) => {
                                const value = event.target.value;
                                setDebtorId(value);
                                if (value) {
                                    const found = debtors.find((d) => String(d.id) === value);
                                    setDebtorName(found?.name ?? '');
                                } else {
                                    setDebtorName('');
                                }
                                clearField('debtor_name');
                            }}
                        >
                            <option value="">
                                {debtorsLoading ? 'Carregando…' : 'Selecione a pessoa'}
                            </option>
                            {debtors.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </select>
                        {onCreateDebtor ? (
                            <button
                                type="button"
                                className="self-start text-caption font-medium text-brand hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
                                disabled={submitting}
                                onClick={onCreateDebtor}
                            >
                                + Cadastrar nova pessoa
                            </button>
                        ) : null}
                        {!debtorId ? (
                            <Input
                                value={debtorName}
                                maxLength={160}
                                disabled={submitting}
                                invalid={Boolean(fieldErrors.debtor_name)}
                                placeholder="Ou digite o nome (cria a pessoa automaticamente)"
                                onChange={(event) => {
                                    setDebtorName(event.target.value);
                                    clearField('debtor_name');
                                }}
                            />
                        ) : null}
                    </div>
                </Field>

                <fieldset className="flex flex-col gap-2" disabled={submitting}>
                    <legend className="text-caption font-medium text-ink-secondary">Tipo</legend>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Tipo">
                        {Object.values(LOAN_KINDS).map((id) => (
                            <Choice
                                key={id}
                                active={kind === id}
                                onClick={() => {
                                    setKind(id);
                                    clearField('kind');
                                    if (id === LOAN_KINDS.cash) {
                                        setCreditCardId('');
                                        clearField('credit_card_id');
                                    }
                                }}
                            >
                                {LOAN_KIND_LABELS[id]}
                            </Choice>
                        ))}
                    </div>
                    {fieldErrors.kind ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.kind}
                        </p>
                    ) : null}
                </fieldset>

                {kind === LOAN_KINDS.card_limit ? (
                    <Field
                        label="Cartão"
                        htmlFor={`${formId}-card`}
                        error={fieldErrors.credit_card_id}
                    >
                        <select
                            id={`${formId}-card`}
                            value={creditCardId}
                            disabled={submitting || creditCardsLoading}
                            aria-invalid={Boolean(fieldErrors.credit_card_id) || undefined}
                            className={selectClass(Boolean(fieldErrors.credit_card_id))}
                            onChange={(event) => {
                                setCreditCardId(event.target.value);
                                clearField('credit_card_id');
                            }}
                        >
                            <option value="">
                                {creditCardsLoading ? 'Carregando…' : 'Selecione o cartão'}
                            </option>
                            {cardOptions.map((card) => (
                                <option key={card.id} value={card.id}>
                                    {card.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                ) : null}

                <Field label="Valor" htmlFor={`${formId}-amount`} error={fieldErrors.amount}>
                    <Input
                        id={`${formId}-amount`}
                        inputMode="decimal"
                        value={amount}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.amount)}
                        placeholder="0.00"
                        onChange={(event) => {
                            setAmount(event.target.value);
                            clearField('amount');
                        }}
                    />
                </Field>

                <div className="grid grid-cols-2 gap-3">
                    <Field
                        label="Emprestado em"
                        htmlFor={`${formId}-lent`}
                        error={fieldErrors.lent_on}
                    >
                        <DateInput
                            id={`${formId}-lent`}
                            value={lentOn}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.lent_on)}
                            onChange={(event) => {
                                setLentOn(event.target.value);
                                clearField('lent_on');
                                if (!expenseOccurredOn || expenseOccurredOn === lentOn) {
                                    setExpenseOccurredOn(event.target.value);
                                }
                            }}
                        />
                    </Field>
                    <Field
                        label="Cobrar em"
                        htmlFor={`${formId}-due`}
                        error={fieldErrors.due_on}
                    >
                        <DateInput
                            id={`${formId}-due`}
                            value={dueOn}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.due_on)}
                            onChange={(event) => {
                                setDueOn(event.target.value);
                                clearField('due_on');
                            }}
                        />
                    </Field>
                </div>

                <Field label="Notas" htmlFor={`${formId}-notes`} error={fieldErrors.notes}>
                    <textarea
                        id={`${formId}-notes`}
                        rows={2}
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

                {mode === 'create' ? (
                    <div className="flex flex-col gap-3 rounded-lg border border-border-subtle bg-surface-raised/40 p-3">
                        <label className="flex items-start gap-2 text-body text-ink">
                            <input
                                type="checkbox"
                                className="mt-1 h-4 w-4 rounded border-border text-brand focus-visible:ring-brand"
                                checked={createExpense}
                                disabled={submitting}
                                onChange={(event) => {
                                    setCreateExpense(event.target.checked);
                                    clearField('create_expense');
                                    clearField('expense_description');
                                }}
                            />
                            <span>
                                <span className="font-medium">Registrar também a saída</span>
                                <span className="mt-0.5 block text-caption text-ink-muted">
                                    Quando a pessoa pegou o valor para comprar algo — cria um
                                    débito vinculado a esta cobrança (não marca como pago).
                                </span>
                            </span>
                        </label>

                        {fieldErrors.create_expense ? (
                            <p className="text-caption text-feedback-danger" role="alert">
                                {fieldErrors.create_expense}
                            </p>
                        ) : null}

                        {createExpense ? (
                            <>
                                <Field
                                    label="O que foi comprado"
                                    htmlFor={`${formId}-expense-desc`}
                                    error={fieldErrors.expense_description}
                                >
                                    <Input
                                        id={`${formId}-expense-desc`}
                                        value={expenseDescription}
                                        maxLength={500}
                                        disabled={submitting}
                                        invalid={Boolean(fieldErrors.expense_description)}
                                        placeholder="Ex.: Mercado, Uber, farmácia…"
                                        onChange={(event) => {
                                            setExpenseDescription(event.target.value);
                                            clearField('expense_description');
                                        }}
                                    />
                                </Field>

                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label="Valor da saída"
                                        htmlFor={`${formId}-expense-amount`}
                                        error={fieldErrors.expense_amount}
                                    >
                                        <Input
                                            id={`${formId}-expense-amount`}
                                            inputMode="decimal"
                                            value={expenseAmount}
                                            disabled={submitting}
                                            invalid={Boolean(fieldErrors.expense_amount)}
                                            placeholder={amount || 'Igual ao empréstimo'}
                                            onChange={(event) => {
                                                setExpenseAmount(event.target.value);
                                                clearField('expense_amount');
                                            }}
                                        />
                                    </Field>
                                    <Field
                                        label="Data da saída"
                                        htmlFor={`${formId}-expense-date`}
                                        error={fieldErrors.expense_occurred_on}
                                    >
                                        <DateInput
                                            id={`${formId}-expense-date`}
                                            value={expenseOccurredOn}
                                            disabled={submitting}
                                            invalid={Boolean(fieldErrors.expense_occurred_on)}
                                            onChange={(event) => {
                                                setExpenseOccurredOn(event.target.value);
                                                clearField('expense_occurred_on');
                                            }}
                                        />
                                    </Field>
                                </div>

                                <Field
                                    label="Categoria"
                                    htmlFor={`${formId}-expense-cat`}
                                    error={fieldErrors.expense_category_id}
                                >
                                    <CategoryFormSelect
                                        id={`${formId}-expense-cat`}
                                        categories={categories}
                                        value={expenseCategoryId}
                                        disabled={submitting || categoriesLoading}
                                        invalid={Boolean(fieldErrors.expense_category_id)}
                                        emptyLabel="Sem categoria"
                                        onChange={(value) => {
                                            setExpenseCategoryId(value);
                                            clearField('expense_category_id');
                                        }}
                                        onCreated={(category) => {
                                            onCategoryCreated?.(category);
                                            setExpenseCategoryId(String(category.id));
                                        }}
                                    />
                                </Field>
                            </>
                        ) : null}
                    </div>
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

function Choice({ active, onClick, children }) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            onClick={onClick}
            className={cx(
                'inline-flex h-10 items-center justify-center rounded-full px-4 text-caption font-medium transition',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                active
                    ? 'bg-brand text-ink-on-brand'
                    : 'border border-border bg-transparent text-ink hover:bg-surface-raised',
            )}
        >
            {children}
        </button>
    );
}

function selectClass(invalid) {
    return cx(
        'h-11 w-full rounded-lg border bg-surface-sunken px-3 font-sans text-body text-ink',
        'outline-none transition-[border-color,box-shadow]',
        'focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
        'disabled:cursor-not-allowed disabled:opacity-60',
        invalid ? 'border-feedback-danger' : 'border-border',
    );
}
