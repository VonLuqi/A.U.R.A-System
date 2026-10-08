import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { countTransactions } from '../../api/transactions';
import { useAuth } from '../../hooks/useAuth';
import {
    useDeleteAvatar,
    useUpdateProfile,
    useUploadAvatar,
} from '../../hooks/useProfileMutations';
import { useWipeAllTransactions } from '../../hooks/useTransactionMutations';
import { cx } from '../../lib/cx';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';
import ConfirmWipeTransactionsDialog, {
    WIPE_CONFIRMATION_PHRASE,
} from '../transactions/ConfirmWipeTransactionsDialog';
import AvatarUploader from './AvatarUploader';

/**
 * ProfileSettingsModal — Etapa I §3.4 (opção A: modal via TopNav / UserMenu).
 *
 * @param {{
 *   open: boolean,
 *   onClose: () => void,
 * }} props
 */
export default function ProfileSettingsModal({ open, onClose }) {
    const formId = useId();
    const nameRef = useRef(null);
    const { user } = useAuth();
    const updateProfile = useUpdateProfile();
    const uploadAvatar = useUploadAvatar();
    const deleteAvatar = useDeleteAvatar();
    const wipeAll = useWipeAllTransactions({
        onSuccess: () => {
            setWipeOpen(false);
            onClose();
        },
    });

    const [name, setName] = useState('');
    const [expenseCycleDay, setExpenseCycleDay] = useState('6');
    const [incomeCycleDay, setIncomeCycleDay] = useState('12');
    const [passwordOpen, setPasswordOpen] = useState(false);
    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');

    const [nameError, setNameError] = useState('');
    const [expenseCycleDayError, setExpenseCycleDayError] = useState('');
    const [incomeCycleDayError, setIncomeCycleDayError] = useState('');
    const [currentPasswordError, setCurrentPasswordError] = useState('');
    const [passwordError, setPasswordError] = useState('');
    const [passwordConfirmationError, setPasswordConfirmationError] = useState('');
    const [avatarError, setAvatarError] = useState('');
    const [formError, setFormError] = useState('');
    const [wipeOpen, setWipeOpen] = useState(false);
    const [wipeCount, setWipeCount] = useState(/** @type {number|null} */ (null));
    const [wipeCountLoading, setWipeCountLoading] = useState(false);

    const submitting = updateProfile.isLoading;
    const avatarBusy = uploadAvatar.isLoading || deleteAvatar.isLoading;
    const wipeBusy = wipeAll.isLoading;

    useEffect(() => {
        if (!open || !user) {
            return;
        }

        setName(user.name ?? '');
        setExpenseCycleDay(String(user.expense_cycle_day ?? 6));
        setIncomeCycleDay(String(user.income_cycle_day ?? 12));
        setPasswordOpen(false);
        setCurrentPassword('');
        setPassword('');
        setPasswordConfirmation('');
        setNameError('');
        setExpenseCycleDayError('');
        setIncomeCycleDayError('');
        setCurrentPasswordError('');
        setPasswordError('');
        setPasswordConfirmationError('');
        setAvatarError('');
        setFormError('');
        setWipeOpen(false);
        setWipeCount(null);
    }, [open, user]);

    useEffect(() => {
        if (!wipeOpen) {
            return undefined;
        }

        const controller = new AbortController();
        setWipeCountLoading(true);
        setWipeCount(null);

        countTransactions({ signal: controller.signal })
            .then((response) => {
                setWipeCount(Number(response?.data?.total ?? 0));
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setWipeCount(null);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setWipeCountLoading(false);
                }
            });

        return () => controller.abort();
    }, [wipeOpen]);

    function clearFieldErrors() {
        setNameError('');
        setExpenseCycleDayError('');
        setIncomeCycleDayError('');
        setCurrentPasswordError('');
        setPasswordError('');
        setPasswordConfirmationError('');
        setFormError('');
    }

    function mapValidation(validation) {
        if (!validation) {
            return false;
        }

        if (validation.name?.[0]) {
            setNameError(validation.name[0]);
        }
        if (validation.expense_cycle_day?.[0]) {
            setExpenseCycleDayError(validation.expense_cycle_day[0]);
        }
        if (validation.income_cycle_day?.[0]) {
            setIncomeCycleDayError(validation.income_cycle_day[0]);
        }
        if (validation.current_password?.[0]) {
            setCurrentPasswordError(validation.current_password[0]);
            setPasswordOpen(true);
        }
        if (validation.password?.[0]) {
            setPasswordError(validation.password[0]);
            setPasswordOpen(true);
        }
        if (validation.password_confirmation?.[0]) {
            setPasswordConfirmationError(validation.password_confirmation[0]);
            setPasswordOpen(true);
        }
        if (validation.avatar?.[0]) {
            setAvatarError(validation.avatar[0]);
        }

        return Boolean(
            validation.name
            || validation.expense_cycle_day
            || validation.income_cycle_day
            || validation.current_password
            || validation.password
            || validation.password_confirmation
            || validation.avatar,
        );
    }

    async function handleSubmit(event) {
        event.preventDefault();
        clearFieldErrors();

        const trimmed = name.trim();
        if (trimmed.length < 2) {
            setNameError('O nome deve ter no mínimo 2 caracteres.');
            return;
        }

        const expenseDay = Number.parseInt(expenseCycleDay, 10);
        const incomeDay = Number.parseInt(incomeCycleDay, 10);
        if (!Number.isFinite(expenseDay) || expenseDay < 1 || expenseDay > 31) {
            setExpenseCycleDayError('Informe um dia entre 1 e 31.');
            return;
        }
        if (!Number.isFinite(incomeDay) || incomeDay < 1 || incomeDay > 31) {
            setIncomeCycleDayError('Informe um dia entre 1 e 31.');
            return;
        }

        /** @type {{
         *   name: string,
         *   expense_cycle_day: number,
         *   income_cycle_day: number,
         *   current_password?: string,
         *   password?: string,
         *   password_confirmation?: string
         * }} */
        const payload = {
            name: trimmed,
            expense_cycle_day: expenseDay,
            income_cycle_day: incomeDay,
        };

        if (passwordOpen) {
            if (!currentPassword) {
                setCurrentPasswordError('Informe a senha atual para definir uma nova.');
                return;
            }
            if (password.length < 8) {
                setPasswordError('A senha deve ter no mínimo 8 caracteres.');
                return;
            }
            if (password !== passwordConfirmation) {
                setPasswordConfirmationError('A confirmação da senha não confere.');
                return;
            }
            payload.current_password = currentPassword;
            payload.password = password;
            payload.password_confirmation = passwordConfirmation;
        }

        try {
            await updateProfile.mutate(payload);
            onClose();
        } catch (error) {
            if (mapValidation(getValidationErrors(error))) {
                return;
            }
            setFormError(getErrorMessage(error));
        }
    }

    async function handleUpload(file) {
        setAvatarError('');

        try {
            await uploadAvatar.mutate(file);
        } catch (error) {
            const validation = getValidationErrors(error);
            if (validation?.avatar?.[0]) {
                setAvatarError(validation.avatar[0]);
                return;
            }
            setAvatarError(getErrorMessage(error));
        }
    }

    async function handleRemoveAvatar() {
        setAvatarError('');

        try {
            await deleteAvatar.mutate();
        } catch (error) {
            setAvatarError(getErrorMessage(error));
        }
    }

    if (!user) {
        return null;
    }

    return (
        <>
        <Modal
            open={open}
            title="Minha conta"
            description="Atualize seu nome, senha e foto de perfil."
            onClose={onClose}
            closeOnScrim={!submitting && !avatarBusy && !wipeBusy && !wipeOpen}
            initialFocusRef={nameRef}
            size="md"
            footer={(
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={submitting || avatarBusy || wipeBusy}
                        onClick={onClose}
                    >
                        Fechar
                    </Button>
                    <Button
                        type="submit"
                        form={formId}
                        size="sm"
                        loading={submitting}
                        disabled={submitting || avatarBusy || wipeBusy}
                    >
                        Salvar
                    </Button>
                </>
            )}
        >
            <form
                id={formId}
                className="flex flex-col gap-5"
                onSubmit={handleSubmit}
                noValidate
            >
                <AvatarUploader
                    user={user}
                    loading={avatarBusy}
                    error={avatarError}
                    onUpload={handleUpload}
                    onRemove={handleRemoveAvatar}
                />

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${formId}-name`}>Nome</Label>
                    <Input
                        ref={nameRef}
                        id={`${formId}-name`}
                        value={name}
                        maxLength={120}
                        disabled={submitting}
                        invalid={Boolean(nameError)}
                        autoComplete="name"
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
                    <Label htmlFor={`${formId}-email`}>E-mail</Label>
                    <Input
                        id={`${formId}-email`}
                        value={user.email ?? ''}
                        disabled
                        readOnly
                        autoComplete="email"
                    />
                    <p className="text-caption text-ink-muted">
                        Alteração de e-mail via administrador.
                    </p>
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${formId}-expense-cycle`}>Dia do ciclo de gastos</Label>
                        <Input
                            id={`${formId}-expense-cycle`}
                            type="number"
                            min={1}
                            max={31}
                            value={expenseCycleDay}
                            disabled={submitting}
                            invalid={Boolean(expenseCycleDayError)}
                            onChange={(event) => {
                                setExpenseCycleDay(event.target.value);
                                setExpenseCycleDayError('');
                            }}
                        />
                        {expenseCycleDayError ? (
                            <p className="text-caption text-feedback-danger" role="alert">
                                {expenseCycleDayError}
                            </p>
                        ) : (
                            <p className="text-caption text-ink-muted">
                                Meu ciclo em Saídas / Todos (também no filtro).
                            </p>
                        )}
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor={`${formId}-income-cycle`}>Dia do ciclo de entradas</Label>
                        <Input
                            id={`${formId}-income-cycle`}
                            type="number"
                            min={1}
                            max={31}
                            value={incomeCycleDay}
                            disabled={submitting}
                            invalid={Boolean(incomeCycleDayError)}
                            onChange={(event) => {
                                setIncomeCycleDay(event.target.value);
                                setIncomeCycleDayError('');
                            }}
                        />
                        {incomeCycleDayError ? (
                            <p className="text-caption text-feedback-danger" role="alert">
                                {incomeCycleDayError}
                            </p>
                        ) : (
                            <p className="text-caption text-ink-muted">
                                Meu ciclo em Entradas (também no filtro).
                            </p>
                        )}
                    </div>
                </div>

                <div className="border-t border-border-subtle pt-4">
                    <button
                        type="button"
                        className={cx(
                            'flex w-full items-center justify-between gap-2 rounded-sm text-left text-body font-medium text-ink',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                        )}
                        aria-expanded={passwordOpen}
                        onClick={() => setPasswordOpen((openPw) => !openPw)}
                    >
                        Alterar senha
                        <ChevronDown
                            size={18}
                            strokeWidth={1.75}
                            className={cx(
                                'shrink-0 text-ink-secondary transition-transform',
                                passwordOpen && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </button>

                    {passwordOpen ? (
                        <div className="mt-3 flex flex-col gap-3">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor={`${formId}-current-password`}>Senha atual</Label>
                                <Input
                                    id={`${formId}-current-password`}
                                    type="password"
                                    value={currentPassword}
                                    disabled={submitting}
                                    invalid={Boolean(currentPasswordError)}
                                    autoComplete="current-password"
                                    onChange={(event) => {
                                        setCurrentPassword(event.target.value);
                                        setCurrentPasswordError('');
                                    }}
                                />
                                {currentPasswordError ? (
                                    <p className="text-caption text-feedback-danger" role="alert">
                                        {currentPasswordError}
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor={`${formId}-password`}>Nova senha</Label>
                                <Input
                                    id={`${formId}-password`}
                                    type="password"
                                    value={password}
                                    disabled={submitting}
                                    invalid={Boolean(passwordError)}
                                    autoComplete="new-password"
                                    onChange={(event) => {
                                        setPassword(event.target.value);
                                        setPasswordError('');
                                    }}
                                />
                                {passwordError ? (
                                    <p className="text-caption text-feedback-danger" role="alert">
                                        {passwordError}
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor={`${formId}-password-confirmation`}>
                                    Confirmar nova senha
                                </Label>
                                <Input
                                    id={`${formId}-password-confirmation`}
                                    type="password"
                                    value={passwordConfirmation}
                                    disabled={submitting}
                                    invalid={Boolean(passwordConfirmationError)}
                                    autoComplete="new-password"
                                    onChange={(event) => {
                                        setPasswordConfirmation(event.target.value);
                                        setPasswordConfirmationError('');
                                    }}
                                />
                                {passwordConfirmationError ? (
                                    <p className="text-caption text-feedback-danger" role="alert">
                                        {passwordConfirmationError}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    ) : null}
                </div>

                {formError ? (
                    <p className="text-caption text-feedback-danger" role="alert">{formError}</p>
                ) : null}

                <div className="border-t border-feedback-danger/40 pt-4">
                    <p className="text-body font-semibold text-feedback-danger">
                        Zona de perigo
                    </p>
                    <p className="mt-1 text-caption text-ink-secondary">
                        Exclui permanentemente todas as suas movimentações. Metas,
                        apelidos, cartões e cobranças não são apagados.
                    </p>
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        className="mt-3"
                        disabled={submitting || avatarBusy || wipeBusy}
                        onClick={() => setWipeOpen(true)}
                    >
                        Excluir todo o histórico
                    </Button>
                </div>
            </form>
        </Modal>

        <ConfirmWipeTransactionsDialog
            open={wipeOpen}
            transactionCount={wipeCount}
            countLoading={wipeCountLoading}
            submitting={wipeBusy}
            onClose={() => {
                if (!wipeBusy) {
                    setWipeOpen(false);
                }
            }}
            onConfirm={async () => {
                await wipeAll.mutate(WIPE_CONFIRMATION_PHRASE);
            }}
        />
        </>
    );
}
