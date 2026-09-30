import { useEffect, useId, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

/**
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   debtor?: { id: number, name: string, notes?: string|null }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function DebtorFormModal({
    open,
    mode = 'create',
    debtor = null,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const nameRef = useRef(null);
    const [name, setName] = useState('');
    const [notes, setNotes] = useState('');
    const [nameError, setNameError] = useState('');
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setNameError('');
        setFormError('');

        if (mode === 'edit' && debtor) {
            setName(debtor.name ?? '');
            setNotes(debtor.notes ?? '');
            return;
        }

        setName('');
        setNotes('');
    }, [open, mode, debtor]);

    async function handleSubmit(event) {
        event.preventDefault();
        setFormError('');
        setNameError('');

        const trimmed = name.trim();
        if (!trimmed) {
            setNameError('Informe o nome da pessoa.');
            return;
        }

        try {
            await onSubmit({
                name: trimmed,
                notes: notes.trim() ? notes.trim() : null,
            });
        } catch (error) {
            const validation = getValidationErrors(error);
            if (validation?.name?.[0]) {
                setNameError(validation.name[0]);
                return;
            }
            setFormError(getErrorMessage(error));
        }
    }

    return (
        <Modal
            open={open}
            title={mode === 'edit' ? 'Editar pessoa' : 'Nova pessoa'}
            description="Cadastre quem costuma pegar dinheiro ou limite com você — depois vincule nos empréstimos ou nas saídas."
            onClose={onClose}
            closeOnScrim={!submitting}
            initialFocusRef={nameRef}
            footer={(
                <>
                    <Button type="button" variant="secondary" size="sm" disabled={submitting} onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" form={formId} size="sm" loading={submitting} disabled={submitting}>
                        {mode === 'edit' ? 'Salvar' : 'Cadastrar'}
                    </Button>
                </>
            )}
        >
            <form id={formId} className="flex flex-col gap-4" onSubmit={handleSubmit} noValidate>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-name`}>Nome</Label>
                    <Input
                        ref={nameRef}
                        id={`${formId}-name`}
                        value={name}
                        maxLength={160}
                        disabled={submitting}
                        invalid={Boolean(nameError)}
                        placeholder="Ex.: Geraldo"
                        onChange={(event) => {
                            setName(event.target.value);
                            setNameError('');
                        }}
                    />
                    {nameError ? (
                        <p className="text-caption text-feedback-danger" role="alert">{nameError}</p>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-notes`}>Notas</Label>
                    <textarea
                        id={`${formId}-notes`}
                        rows={2}
                        maxLength={2000}
                        value={notes}
                        disabled={submitting}
                        className={cx(
                            'w-full resize-y rounded-lg border border-border bg-surface-sunken px-3 py-2.5 font-sans text-body text-ink',
                            'placeholder:text-ink-muted outline-none focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
                            'disabled:cursor-not-allowed disabled:opacity-60',
                        )}
                        placeholder="Opcional"
                        onChange={(event) => setNotes(event.target.value)}
                    />
                </div>

                {formError ? (
                    <p className="text-caption text-feedback-danger" role="alert">{formError}</p>
                ) : null}
            </form>
        </Modal>
    );
}
