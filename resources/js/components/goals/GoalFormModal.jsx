import { useEffect, useId, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import {
    GOAL_KINDS,
    GOAL_KIND_LABELS,
    GOAL_PROGRESS_MODES,
    GOAL_PROGRESS_MODE_LABELS,
    GOAL_STATUSES,
    GOAL_STATUS_LABELS,
    parseGoalAmount,
} from '../../lib/goals';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

const EMPTY_ERRORS = {
    name: '',
    kind: '',
    target_amount: '',
    current_amount: '',
    deadline_on: '',
    progress_mode: '',
    category_id: '',
    linked_description_pattern: '',
    status: '',
};

/**
 * GoalFormModal — criar/editar meta (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   goal?: import('../../api/goals').Goal|null,
 *   categories?: Array<{ id: number, name: string }>,
 *   categoriesLoading?: boolean,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function GoalFormModal({
    open,
    mode = 'create',
    goal = null,
    categories = [],
    categoriesLoading = false,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const nameRef = useRef(null);

    const [name, setName] = useState('');
    const [kind, setKind] = useState(GOAL_KINDS.savings);
    const [targetAmount, setTargetAmount] = useState('');
    const [currentAmount, setCurrentAmount] = useState('');
    const [deadlineOn, setDeadlineOn] = useState('');
    const [progressMode, setProgressMode] = useState(GOAL_PROGRESS_MODES.manual);
    const [categoryId, setCategoryId] = useState('');
    const [pattern, setPattern] = useState('');
    const [status, setStatus] = useState(GOAL_STATUSES.active);
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');

        if (mode === 'edit' && goal) {
            setName(goal.name ?? '');
            setKind(goal.kind === GOAL_KINDS.debt_payoff ? GOAL_KINDS.debt_payoff : GOAL_KINDS.savings);
            setTargetAmount(String(goal.target_amount ?? ''));
            setCurrentAmount(String(goal.current_amount ?? ''));
            setDeadlineOn(goal.deadline_on ?? '');
            setProgressMode(
                goal.progress_mode === GOAL_PROGRESS_MODES.linked
                    ? GOAL_PROGRESS_MODES.linked
                    : GOAL_PROGRESS_MODES.manual,
            );
            setCategoryId(goal.category_id != null ? String(goal.category_id) : '');
            setPattern(goal.linked_description_pattern ?? '');
            setStatus(
                Object.values(GOAL_STATUSES).includes(goal.status)
                    ? goal.status
                    : GOAL_STATUSES.active,
            );
            return;
        }

        setName('');
        setKind(GOAL_KINDS.savings);
        setTargetAmount('');
        setCurrentAmount('0');
        setDeadlineOn('');
        setProgressMode(GOAL_PROGRESS_MODES.manual);
        setCategoryId('');
        setPattern('');
        setStatus(GOAL_STATUSES.active);
    }, [open, mode, goal]);

    function clearField(key) {
        setFieldErrors((prev) => (prev[key] ? { ...prev, [key]: '' } : prev));
    }

    function validateClient() {
        const next = { ...EMPTY_ERRORS };
        const trimmedName = name.trim();

        if (!trimmedName) {
            next.name = 'Informe o nome da meta.';
        } else if (trimmedName.length > 160) {
            next.name = 'O nome deve ter no máximo 160 caracteres.';
        }

        if (kind !== GOAL_KINDS.savings && kind !== GOAL_KINDS.debt_payoff) {
            next.kind = 'Tipo de meta inválido.';
        }

        const target = parseGoalAmount(targetAmount);
        if (!target.ok) {
            next.target_amount = target.message;
        }

        if (currentAmount.trim() !== '') {
            const current = parseGoalAmount(currentAmount, { allowZero: true });
            if (!current.ok) {
                next.current_amount = current.message;
            }
        }

        if (progressMode === GOAL_PROGRESS_MODES.linked) {
            if (!categoryId && !pattern.trim()) {
                next.progress_mode =
                    'Modo vinculado exige categoria e/ou padrão de descrição.';
            }
        }

        if (pattern.length > 255) {
            next.linked_description_pattern = 'O padrão deve ter no máximo 255 caracteres.';
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

        const target = parseGoalAmount(targetAmount);
        const current =
            currentAmount.trim() === ''
                ? null
                : parseGoalAmount(currentAmount, { allowZero: true });

        /** @type {Record<string, unknown>} */
        const payload = {
            name: name.trim(),
            kind,
            target_amount: target.ok ? target.value : targetAmount,
            progress_mode: progressMode,
            deadline_on: deadlineOn || null,
            category_id:
                progressMode === GOAL_PROGRESS_MODES.linked && categoryId
                    ? Number(categoryId)
                    : null,
            linked_description_pattern:
                progressMode === GOAL_PROGRESS_MODES.linked && pattern.trim()
                    ? pattern.trim()
                    : null,
        };

        if (current?.ok) {
            payload.current_amount = current.value;
        } else if (mode === 'create') {
            payload.current_amount = '0.00';
        }

        if (mode === 'edit') {
            payload.status = status;
        }

        if (progressMode === GOAL_PROGRESS_MODES.manual) {
            payload.category_id = null;
            payload.linked_description_pattern = null;
        }

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                setFieldErrors({
                    name: validation.name?.[0] ?? '',
                    kind: validation.kind?.[0] ?? '',
                    target_amount: validation.target_amount?.[0] ?? '',
                    current_amount: validation.current_amount?.[0] ?? '',
                    deadline_on: validation.deadline_on?.[0] ?? '',
                    progress_mode: validation.progress_mode?.[0] ?? '',
                    category_id: validation.category_id?.[0] ?? '',
                    linked_description_pattern:
                        validation.linked_description_pattern?.[0] ?? '',
                    status: validation.status?.[0] ?? '',
                });
                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    const title = mode === 'edit' ? 'Editar meta' : 'Nova meta';

    return (
        <Modal
            open={open}
            title={title}
            description="Defina poupança ou amortização com progresso manual ou vinculado a lançamentos."
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
                        maxLength={160}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.name)}
                        onChange={(event) => {
                            setName(event.target.value);
                            clearField('name');
                        }}
                    />
                </Field>

                <fieldset className="flex flex-col gap-2" disabled={submitting}>
                    <legend className="text-caption font-medium text-ink-secondary">Tipo</legend>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Tipo de meta">
                        {Object.values(GOAL_KINDS).map((id) => (
                            <Choice
                                key={id}
                                active={kind === id}
                                onClick={() => {
                                    setKind(id);
                                    clearField('kind');
                                }}
                            >
                                {GOAL_KIND_LABELS[id]}
                            </Choice>
                        ))}
                    </div>
                    {fieldErrors.kind ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.kind}
                        </p>
                    ) : null}
                </fieldset>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field
                        label="Valor-alvo"
                        htmlFor={`${formId}-target`}
                        error={fieldErrors.target_amount}
                    >
                        <Input
                            id={`${formId}-target`}
                            type="text"
                            inputMode="decimal"
                            placeholder="0,00"
                            value={targetAmount}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.target_amount)}
                            onChange={(event) => {
                                setTargetAmount(event.target.value);
                                clearField('target_amount');
                            }}
                        />
                    </Field>

                    <Field
                        label="Progresso atual"
                        htmlFor={`${formId}-current`}
                        error={fieldErrors.current_amount}
                    >
                        <Input
                            id={`${formId}-current`}
                            type="text"
                            inputMode="decimal"
                            placeholder="0,00"
                            value={currentAmount}
                            disabled={submitting || progressMode === GOAL_PROGRESS_MODES.linked}
                            invalid={Boolean(fieldErrors.current_amount)}
                            onChange={(event) => {
                                setCurrentAmount(event.target.value);
                                clearField('current_amount');
                            }}
                        />
                    </Field>
                </div>

                <Field
                    label="Prazo (opcional)"
                    htmlFor={`${formId}-deadline`}
                    error={fieldErrors.deadline_on}
                >
                    <Input
                        id={`${formId}-deadline`}
                        type="date"
                        value={deadlineOn}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.deadline_on)}
                        onChange={(event) => {
                            setDeadlineOn(event.target.value);
                            clearField('deadline_on');
                        }}
                    />
                </Field>

                <fieldset className="flex flex-col gap-2" disabled={submitting}>
                    <legend className="text-caption font-medium text-ink-secondary">
                        Modo de progresso
                    </legend>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Modo de progresso">
                        {Object.values(GOAL_PROGRESS_MODES).map((id) => (
                            <Choice
                                key={id}
                                active={progressMode === id}
                                onClick={() => {
                                    setProgressMode(id);
                                    clearField('progress_mode');
                                }}
                            >
                                {GOAL_PROGRESS_MODE_LABELS[id]}
                            </Choice>
                        ))}
                    </div>
                    {fieldErrors.progress_mode ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.progress_mode}
                        </p>
                    ) : null}
                </fieldset>

                {progressMode === GOAL_PROGRESS_MODES.linked ? (
                    <>
                        <Field
                            label="Categoria vinculada"
                            htmlFor={`${formId}-category`}
                            error={fieldErrors.category_id}
                        >
                            <select
                                id={`${formId}-category`}
                                value={categoryId}
                                disabled={submitting || categoriesLoading}
                                className={selectClass(Boolean(fieldErrors.category_id))}
                                onChange={(event) => {
                                    setCategoryId(event.target.value);
                                    clearField('category_id');
                                    clearField('progress_mode');
                                }}
                            >
                                <option value="">Nenhuma</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            label="Padrão na descrição"
                            htmlFor={`${formId}-pattern`}
                            error={fieldErrors.linked_description_pattern}
                        >
                            <Input
                                id={`${formId}-pattern`}
                                value={pattern}
                                maxLength={255}
                                placeholder="Ex.: Netflix"
                                disabled={submitting}
                                invalid={Boolean(fieldErrors.linked_description_pattern)}
                                onChange={(event) => {
                                    setPattern(event.target.value);
                                    clearField('linked_description_pattern');
                                    clearField('progress_mode');
                                }}
                            />
                        </Field>
                    </>
                ) : null}

                {mode === 'edit' ? (
                    <Field label="Status" htmlFor={`${formId}-status`} error={fieldErrors.status}>
                        <select
                            id={`${formId}-status`}
                            value={status}
                            disabled={submitting}
                            className={selectClass(Boolean(fieldErrors.status))}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                clearField('status');
                            }}
                        >
                            {Object.values(GOAL_STATUSES).map((id) => (
                                <option key={id} value={id}>
                                    {GOAL_STATUS_LABELS[id]}
                                </option>
                            ))}
                        </select>
                    </Field>
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
