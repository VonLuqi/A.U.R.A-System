import { useEffect, useId, useRef, useState } from 'react';
import {
    CATEGORY_TYPES,
    CATEGORY_TYPE_LABELS,
    DEFAULT_CATEGORY_COLOR,
} from '../../lib/categories';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';
import Pill from '../ui/Pill';

const EMPTY_ERRORS = {
    name: '',
    type: '',
    color: '',
};

/**
 * CategoryFormModal — criar/editar categoria.
 *
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   category?: {
 *     id?: number,
 *     name?: string,
 *     type?: string,
 *     color?: string|null,
 *     is_system?: boolean,
 *   }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: { name: string, type?: string, color?: string|null }) => Promise<void>,
 * }} props
 */
export default function CategoryFormModal({
    open,
    mode = 'create',
    category = null,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const nameRef = useRef(null);
    const isSystem = Boolean(category?.is_system);

    const [name, setName] = useState('');
    const [type, setType] = useState(CATEGORY_TYPES.expense);
    const [color, setColor] = useState(DEFAULT_CATEGORY_COLOR);
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');

        if (mode === 'edit' && category) {
            setName(category.name ?? '');
            setType(
                Object.values(CATEGORY_TYPES).includes(category.type)
                    ? category.type
                    : CATEGORY_TYPES.expense,
            );
            setColor(category.color || DEFAULT_CATEGORY_COLOR);
            return;
        }

        setName('');
        setType(CATEGORY_TYPES.expense);
        setColor(DEFAULT_CATEGORY_COLOR);
    }, [open, mode, category]);

    function clearField(field) {
        setFieldErrors((prev) => ({ ...prev, [field]: '' }));
        setFormError('');
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setFieldErrors(EMPTY_ERRORS);
        setFormError('');

        const trimmed = name.trim();
        if (!trimmed) {
            setFieldErrors((prev) => ({ ...prev, name: 'Informe o nome da categoria.' }));
            return;
        }

        /** @type {{ name: string, type?: string, color?: string|null }} */
        const payload = {
            name: trimmed,
            color: color || null,
        };

        if (!isSystem) {
            payload.type = type;
        }

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);
            if (validation) {
                setFieldErrors({
                    name: validation.name?.[0] ?? '',
                    type: validation.type?.[0] ?? '',
                    color: validation.color?.[0] ?? '',
                });
                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    return (
        <Modal
            open={open}
            title={mode === 'edit' ? 'Editar categoria' : 'Nova categoria'}
            description={
                isSystem
                    ? 'Categoria do sistema: você pode alterar nome e cor.'
                    : 'Organize entradas, saídas e transferências.'
            }
            onClose={onClose}
            closeOnScrim={!submitting}
            initialFocusRef={nameRef}
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
                        {mode === 'edit' ? 'Salvar' : 'Criar'}
                    </Button>
                </>
            )}
        >
            <form
                id={formId}
                className="flex flex-col gap-4"
                onSubmit={handleSubmit}
                noValidate
            >
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-name`}>Nome</Label>
                    <Input
                        ref={nameRef}
                        id={`${formId}-name`}
                        value={name}
                        maxLength={80}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.name)}
                        onChange={(event) => {
                            setName(event.target.value);
                            clearField('name');
                        }}
                    />
                    {fieldErrors.name ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.name}
                        </p>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1.5">
                    <span className="text-caption font-medium text-ink-secondary">Tipo</span>
                    <div className="flex flex-wrap gap-2" role="group" aria-label="Tipo">
                        {Object.values(CATEGORY_TYPES).map((id) => (
                            <Pill
                                key={id}
                                active={type === id}
                                disabled={submitting || isSystem}
                                onClick={() => {
                                    if (isSystem) {
                                        return;
                                    }
                                    setType(id);
                                    clearField('type');
                                }}
                            >
                                {CATEGORY_TYPE_LABELS[id]}
                            </Pill>
                        ))}
                    </div>
                    {isSystem ? (
                        <p className="text-caption text-ink-muted">
                            Tipo travado em categorias do sistema.
                        </p>
                    ) : null}
                    {fieldErrors.type ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.type}
                        </p>
                    ) : null}
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-color`}>Cor</Label>
                    <div className="flex items-center gap-3">
                        <input
                            id={`${formId}-color`}
                            type="color"
                            value={color || DEFAULT_CATEGORY_COLOR}
                            disabled={submitting}
                            className="h-11 w-14 cursor-pointer rounded-lg border border-border bg-surface-sunken p-1"
                            onChange={(event) => {
                                setColor(event.target.value.toUpperCase());
                                clearField('color');
                            }}
                        />
                        <Input
                            value={color || ''}
                            maxLength={7}
                            disabled={submitting}
                            invalid={Boolean(fieldErrors.color)}
                            placeholder="#DCCFFF"
                            className="flex-1 font-mono uppercase"
                            onChange={(event) => {
                                setColor(event.target.value);
                                clearField('color');
                            }}
                        />
                    </div>
                    {fieldErrors.color ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.color}
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
