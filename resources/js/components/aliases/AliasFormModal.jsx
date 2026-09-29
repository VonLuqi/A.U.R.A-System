import { useEffect, useId, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import {
    ALIAS_MATCH_TYPES,
    ALIAS_MATCH_TYPE_LABELS,
    ALIAS_RETROACTIVE_LIMIT,
    applyToExistingWarning,
} from '../../lib/aliases';
import { previewAlias } from '../../api/aliases';
import Button from '../ui/Button';
import CategoryFormSelect from '../ui/CategoryFormSelect';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

const EMPTY_ERRORS = {
    match_type: '',
    match_pattern: '',
    display_name: '',
    category_id: '',
    priority: '',
    is_active: '',
};

/**
 * AliasFormModal — criar/editar + preview (PLAN_EXPANSAO §8.7).
 *
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   alias?: import('../../api/aliases').Alias|null,
 *   categories?: Array<{ id: number, name: string }>,
 *   categoriesLoading?: boolean,
 *   onCategoryCreated?: (category: { id: number, name: string }) => void,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function AliasFormModal({
    open,
    mode = 'create',
    alias = null,
    categories = [],
    categoriesLoading = false,
    onCategoryCreated,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const patternRef = useRef(null);

    const [matchType, setMatchType] = useState(ALIAS_MATCH_TYPES.contains);
    const [matchPattern, setMatchPattern] = useState('');
    const [displayName, setDisplayName] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [priority, setPriority] = useState('100');
    const [isActive, setIsActive] = useState(true);
    const [applyToExisting, setApplyToExisting] = useState(true);
    const [previewText, setPreviewText] = useState('');
    const [previewResult, setPreviewResult] = useState(/** @type {null|false|object} */ (null));
    const [previewStatus, setPreviewStatus] = useState('idle');
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');
        setApplyToExisting(true);
        setPreviewText('');
        setPreviewResult(null);
        setPreviewStatus('idle');

        if (mode === 'edit' && alias) {
            setMatchType(
                Object.values(ALIAS_MATCH_TYPES).includes(alias.match_type)
                    ? alias.match_type
                    : ALIAS_MATCH_TYPES.contains,
            );
            setMatchPattern(alias.match_pattern ?? '');
            setDisplayName(alias.display_name ?? '');
            setCategoryId(alias.category_id != null ? String(alias.category_id) : '');
            setPriority(String(alias.priority ?? 100));
            setIsActive(Boolean(alias.is_active));
            return;
        }

        setMatchType(ALIAS_MATCH_TYPES.contains);
        setMatchPattern('');
        setDisplayName('');
        setCategoryId('');
        setPriority('100');
        setIsActive(true);
    }, [open, mode, alias]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const description = previewText.trim();

        if (description.length < 2) {
            setPreviewResult(null);
            setPreviewStatus('idle');
            return undefined;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setPreviewStatus('loading');

            try {
                const match = await previewAlias(
                    { description },
                    { signal: controller.signal },
                );
                setPreviewResult(match ?? false);
                setPreviewStatus('success');
            } catch (error) {
                if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
                    return;
                }
                setPreviewStatus('error');
                setPreviewResult(null);
            }
        }, 350);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [open, previewText]);

    function clearField(key) {
        setFieldErrors((prev) => (prev[key] ? { ...prev, [key]: '' } : prev));
    }

    function validateClient() {
        const next = { ...EMPTY_ERRORS };

        if (!Object.values(ALIAS_MATCH_TYPES).includes(matchType)) {
            next.match_type = 'Tipo de correspondência inválido.';
        }

        if (!matchPattern.trim()) {
            next.match_pattern = 'Informe o padrão.';
        } else if (matchPattern.trim().length > 255) {
            next.match_pattern = 'O padrão deve ter no máximo 255 caracteres.';
        }

        if (!displayName.trim()) {
            next.display_name = 'Informe o apelido.';
        } else if (displayName.trim().length > 255) {
            next.display_name = 'O apelido deve ter no máximo 255 caracteres.';
        }

        const priorityNum = Number(priority);
        if (!Number.isInteger(priorityNum) || priorityNum < 0 || priorityNum > 10000) {
            next.priority = 'Prioridade entre 0 e 10000.';
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
        const payload = {
            match_type: matchType,
            match_pattern: matchPattern.trim(),
            display_name: displayName.trim(),
            category_id: categoryId === '' ? null : Number(categoryId),
            priority: Number(priority),
            is_active: isActive,
        };

        if (mode === 'create') {
            payload.apply_to_existing = applyToExisting;
        }

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                setFieldErrors({
                    match_type: validation.match_type?.[0] ?? '',
                    match_pattern: validation.match_pattern?.[0] ?? '',
                    display_name: validation.display_name?.[0] ?? '',
                    category_id: validation.category_id?.[0] ?? '',
                    priority: validation.priority?.[0] ?? '',
                    is_active: validation.is_active?.[0] ?? '',
                });
                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    const title = mode === 'edit' ? 'Editar apelido' : 'Novo apelido';

    return (
        <Modal
            open={open}
            title={title}
            description="Renomeie e categorize lançamentos automaticamente por padrão de descrição."
            onClose={onClose}
            closeOnScrim={!submitting}
            initialFocusRef={patternRef}
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
                <fieldset className="flex flex-col gap-2" disabled={submitting}>
                    <legend className="text-caption font-medium text-ink-secondary">
                        Tipo de correspondência
                    </legend>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Tipo">
                        {Object.values(ALIAS_MATCH_TYPES).map((id) => (
                            <Choice
                                key={id}
                                active={matchType === id}
                                onClick={() => {
                                    setMatchType(id);
                                    clearField('match_type');
                                }}
                            >
                                {ALIAS_MATCH_TYPE_LABELS[id]}
                            </Choice>
                        ))}
                    </div>
                    {fieldErrors.match_type ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.match_type}
                        </p>
                    ) : null}
                </fieldset>

                <Field
                    label="Padrão"
                    htmlFor={`${formId}-pattern`}
                    error={fieldErrors.match_pattern}
                >
                    <Input
                        ref={patternRef}
                        id={`${formId}-pattern`}
                        value={matchPattern}
                        maxLength={255}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.match_pattern)}
                        placeholder={
                            matchType === 'regex' ? 'Ex.: uber.*trip' : 'Ex.: Uber'
                        }
                        onChange={(event) => {
                            setMatchPattern(event.target.value);
                            clearField('match_pattern');
                        }}
                    />
                </Field>

                <Field
                    label="Apelido (nome de exibição)"
                    htmlFor={`${formId}-display`}
                    error={fieldErrors.display_name}
                >
                    <Input
                        id={`${formId}-display`}
                        value={displayName}
                        maxLength={255}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.display_name)}
                        onChange={(event) => {
                            setDisplayName(event.target.value);
                            clearField('display_name');
                        }}
                    />
                </Field>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                    <Field
                        label="Prioridade"
                        htmlFor={`${formId}-priority`}
                        error={fieldErrors.priority}
                    >
                        <Input
                            id={`${formId}-priority`}
                            type="number"
                            min={0}
                            max={10000}
                            value={priority}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.priority)}
                            onChange={(event) => {
                                setPriority(event.target.value);
                                clearField('priority');
                            }}
                        />
                    </Field>
                </div>
                <p className="text-small text-ink-muted">
                    Menor prioridade vence primeiro (padrão 100).
                </p>

                <label className="flex items-start gap-3 text-caption text-ink-secondary">
                    <input
                        type="checkbox"
                        className="mt-0.5 size-4 rounded border-border bg-surface-sunken text-brand focus-visible:ring-brand"
                        checked={isActive}
                        disabled={submitting}
                        onChange={(event) => setIsActive(event.target.checked)}
                    />
                    <span>Regra ativa</span>
                </label>

                {mode === 'create' ? (
                    <div className="flex flex-col gap-1.5">
                        <label className="flex items-start gap-3 text-caption text-ink-secondary">
                            <input
                                type="checkbox"
                                className="mt-0.5 size-4 rounded border-border bg-surface-sunken text-brand focus-visible:ring-brand"
                                checked={applyToExisting}
                                disabled={submitting}
                                onChange={(event) => setApplyToExisting(event.target.checked)}
                            />
                            <span>Aplicar também a lançamentos existentes</span>
                        </label>
                        {applyToExisting ? (
                            <p className="pl-7 text-small text-ink-muted" role="note">
                                {applyToExistingWarning(ALIAS_RETROACTIVE_LIMIT)}
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface-sunken p-3">
                    <Label htmlFor={`${formId}-preview`}>Testar correspondência</Label>
                    <Input
                        id={`${formId}-preview`}
                        value={previewText}
                        disabled={submitting}
                        placeholder="Cole uma descrição de lançamento…"
                        onChange={(event) => setPreviewText(event.target.value)}
                    />
                    <p className="text-small text-ink-muted" aria-live="polite">
                        {previewStatus === 'loading'
                            ? 'Verificando…'
                            : previewResult && typeof previewResult === 'object'
                              ? `Match: “${previewResult.display_name}”${
                                    previewResult.alias_id
                                        ? ` (regra #${previewResult.alias_id})`
                                        : ''
                                }`
                              : previewResult === false
                                ? 'Nenhuma regra ativa corresponde.'
                                : previewStatus === 'error'
                                  ? 'Não foi possível testar agora.'
                                  : 'Digite ao menos 2 caracteres para testar as regras já salvas.'}
                    </p>
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
