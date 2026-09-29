import { useEffect, useId, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import {
    ALIAS_RETROACTIVE_LIMIT,
    applyToExistingWarning,
} from '../../lib/aliases';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

/**
 * RememberAliasDialog — “Lembrar apelido” a partir de um lançamento (§8.2 / §8.7).
 *
 * @param {{
 *   open: boolean,
 *   transaction?: {
 *     description?: string,
 *     category?: { id?: number }|null,
 *   }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: {
 *     display_name: string,
 *     match_type: 'contains'|'exact',
 *     apply_to_existing: boolean,
 *     category_id?: number|null,
 *   }) => Promise<void>,
 * }} props
 */
export default function RememberAliasDialog({
    open,
    transaction = null,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const [displayName, setDisplayName] = useState('');
    const [matchType, setMatchType] = useState('contains');
    const [applyToExisting, setApplyToExisting] = useState(true);
    const [fieldError, setFieldError] = useState('');
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setDisplayName(transaction?.description?.trim() ?? '');
        setMatchType('contains');
        setApplyToExisting(true);
        setFieldError('');
        setFormError('');
    }, [open, transaction]);

    async function handleSubmit(event) {
        event.preventDefault();
        setFormError('');

        const trimmed = displayName.trim();
        if (!trimmed) {
            setFieldError('Informe o nome de exibição.');
            return;
        }

        if (trimmed.length > 255) {
            setFieldError('O nome deve ter no máximo 255 caracteres.');
            return;
        }

        try {
            await onSubmit({
                display_name: trimmed,
                match_type: matchType,
                apply_to_existing: applyToExisting,
                category_id: transaction?.category?.id ?? null,
            });
        } catch (error) {
            const validation = getValidationErrors(error);
            if (validation?.display_name?.[0]) {
                setFieldError(validation.display_name[0]);
                return;
            }
            if (validation?.match_pattern?.[0]) {
                setFormError(validation.match_pattern[0]);
                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    return (
        <Modal
            open={open}
            title="Lembrar apelido"
            description="Crie uma regra para renomear lançamentos parecidos automaticamente."
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
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        size="sm"
                        loading={submitting}
                        disabled={submitting}
                    >
                        Salvar regra
                    </Button>
                </>
            )}
        >
            <form id={formId} className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
                <div className="flex flex-col gap-2">
                    <Label htmlFor={`${formId}-name`}>Nome de exibição</Label>
                    <Input
                        id={`${formId}-name`}
                        value={displayName}
                        disabled={submitting}
                        invalid={Boolean(fieldError)}
                        maxLength={255}
                        onChange={(event) => {
                            setDisplayName(event.target.value);
                            if (fieldError) {
                                setFieldError('');
                            }
                        }}
                    />
                    {fieldError ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldError}
                        </p>
                    ) : null}
                </div>

                <fieldset className="flex flex-col gap-2" disabled={submitting}>
                    <legend className="text-caption font-medium text-ink-secondary">
                        Como combinar
                    </legend>
                    <div className="flex flex-wrap gap-2">
                        <MatchOption
                            active={matchType === 'contains'}
                            onClick={() => setMatchType('contains')}
                        >
                            Contém
                        </MatchOption>
                        <MatchOption
                            active={matchType === 'exact'}
                            onClick={() => setMatchType('exact')}
                        >
                            Exato
                        </MatchOption>
                    </div>
                </fieldset>

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

                {formError ? (
                    <p className="text-caption text-feedback-danger" role="alert">
                        {formError}
                    </p>
                ) : null}
            </form>
        </Modal>
    );
}

function MatchOption({ active, onClick, children }) {
    return (
        <button
            type="button"
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
