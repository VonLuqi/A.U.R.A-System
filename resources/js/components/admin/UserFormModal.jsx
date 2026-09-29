import { useEffect, useId, useRef, useState } from 'react';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import { ASSIGNABLE_ROLES, USER_ROLE_LABELS } from '../../lib/users';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

const EMPTY_ERRORS = {
    name: '',
    email: '',
    password: '',
    role: '',
    is_active: '',
    user: '',
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * UserFormModal — criar/editar (PLAN_EXPANSAO §8.6).
 * Senha obrigatória no create; opcional no edit (deixar vazio = manter).
 *
 * @param {{
 *   open: boolean,
 *   mode?: 'create'|'edit',
 *   user?: import('../../api/users').AdminUser|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onSubmit: (payload: object) => Promise<void>,
 * }} props
 */
export default function UserFormModal({
    open,
    mode = 'create',
    user = null,
    submitting = false,
    onClose,
    onSubmit,
}) {
    const formId = useId();
    const nameRef = useRef(null);

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [role, setRole] = useState('visitor');
    const [isActive, setIsActive] = useState(true);
    const [fieldErrors, setFieldErrors] = useState(EMPTY_ERRORS);
    const [formError, setFormError] = useState('');

    useEffect(() => {
        if (!open) {
            return;
        }

        setFieldErrors(EMPTY_ERRORS);
        setFormError('');
        setPassword('');

        if (mode === 'edit' && user) {
            setName(user.name ?? '');
            setEmail(user.email ?? '');
            setRole(
                ASSIGNABLE_ROLES.includes(user.role) ? user.role : 'visitor',
            );
            setIsActive(Boolean(user.is_active));
            return;
        }

        setName('');
        setEmail('');
        setRole('visitor');
        setIsActive(true);
    }, [open, mode, user]);

    function clearField(key) {
        setFieldErrors((prev) => (prev[key] ? { ...prev, [key]: '' } : prev));
    }

    function validateClient() {
        const next = { ...EMPTY_ERRORS };
        const trimmedName = name.trim();
        const trimmedEmail = email.trim();

        if (!trimmedName) {
            next.name = 'Informe o nome.';
        }

        if (!trimmedEmail) {
            next.email = 'Informe o e-mail.';
        } else if (!EMAIL_PATTERN.test(trimmedEmail)) {
            next.email = 'Informe um e-mail válido.';
        }

        if (mode === 'create') {
            if (!password) {
                next.password = 'Informe a senha.';
            } else if (password.length < 8) {
                next.password = 'A senha deve ter no mínimo 8 caracteres.';
            }
        } else if (password && password.length < 8) {
            next.password = 'A senha deve ter no mínimo 8 caracteres.';
        }

        if (!ASSIGNABLE_ROLES.includes(role)) {
            next.role = 'Papel inválido. Use subadmin, visitor ou test.';
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
            name: name.trim(),
            email: email.trim(),
            role,
            is_active: isActive,
        };

        if (mode === 'create' || password) {
            payload.password = password;
        }

        try {
            await onSubmit(payload);
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                setFieldErrors({
                    name: validation.name?.[0] ?? '',
                    email: validation.email?.[0] ?? '',
                    password: validation.password?.[0] ?? '',
                    role: validation.role?.[0] ?? '',
                    is_active: validation.is_active?.[0] ?? '',
                    user: validation.user?.[0] ?? '',
                });
                if (validation.user?.[0]) {
                    setFormError(validation.user[0]);
                }
                return;
            }

            setFormError(getErrorMessage(error));
        }
    }

    const title = mode === 'edit' ? 'Editar usuário' : 'Novo usuário';

    return (
        <Modal
            open={open}
            title={title}
            description="Papéis atribuíveis: Subadmin, Visitante ou Teste. Admin não é gerenciado por esta tela."
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
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.name)}
                        autoComplete="name"
                        onChange={(event) => {
                            setName(event.target.value);
                            clearField('name');
                        }}
                    />
                </Field>

                <Field label="E-mail" htmlFor={`${formId}-email`} error={fieldErrors.email}>
                    <Input
                        id={`${formId}-email`}
                        type="email"
                        value={email}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.email)}
                        autoComplete="email"
                        onChange={(event) => {
                            setEmail(event.target.value);
                            clearField('email');
                        }}
                    />
                </Field>

                <Field
                    label={mode === 'edit' ? 'Nova senha (opcional)' : 'Senha'}
                    htmlFor={`${formId}-password`}
                    error={fieldErrors.password}
                >
                    <Input
                        id={`${formId}-password`}
                        type="password"
                        value={password}
                        disabled={submitting}
                        invalid={Boolean(fieldErrors.password)}
                        autoComplete={mode === 'edit' ? 'new-password' : 'new-password'}
                        placeholder={mode === 'edit' ? 'Deixe em branco para manter' : ''}
                        onChange={(event) => {
                            setPassword(event.target.value);
                            clearField('password');
                        }}
                    />
                </Field>

                <fieldset className="flex flex-col gap-2" disabled={submitting}>
                    <legend className="text-caption font-medium text-ink-secondary">Papel</legend>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Papel">
                        {ASSIGNABLE_ROLES.map((id) => (
                            <button
                                key={id}
                                type="button"
                                role="radio"
                                aria-checked={role === id}
                                onClick={() => {
                                    setRole(id);
                                    clearField('role');
                                }}
                                className={cx(
                                    'inline-flex h-10 items-center justify-center rounded-full px-4 text-caption font-medium transition',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                                    role === id
                                        ? 'bg-brand text-ink-on-brand'
                                        : 'border border-border bg-transparent text-ink hover:bg-surface-raised',
                                )}
                            >
                                {USER_ROLE_LABELS[id]}
                            </button>
                        ))}
                    </div>
                    {fieldErrors.role ? (
                        <p className="text-caption text-feedback-danger" role="alert">
                            {fieldErrors.role}
                        </p>
                    ) : null}
                </fieldset>

                <label className="flex items-start gap-3 text-caption text-ink-secondary">
                    <input
                        type="checkbox"
                        className="mt-0.5 size-4 rounded border-border bg-surface-sunken text-brand focus-visible:ring-brand"
                        checked={isActive}
                        disabled={submitting}
                        onChange={(event) => setIsActive(event.target.checked)}
                    />
                    <span>Conta ativa</span>
                </label>

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
