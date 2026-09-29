import { useEffect, useId, useRef, useState } from 'react';
import { ArrowRight, Eye, EyeOff } from 'lucide-react';
import { useNavigate, useLocation } from 'react-router-dom';
import { toast } from 'sonner';
import { isInvalidCredentialsError } from '../../api/auth';
import { useAuth } from '../../hooks/useAuth';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * LoginForm — Etapa D §1.3.2.
 */
export default function LoginForm() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();

    const emailId = useId();
    const passwordId = useId();
    const formErrorId = useId();

    const emailRef = useRef(null);
    const passwordRef = useRef(null);

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState({ email: '', password: '' });
    const [formError, setFormError] = useState('');
    const [focusTarget, setFocusTarget] = useState(null);

    useEffect(() => {
        if (focusTarget === 'email') {
            emailRef.current?.focus();
        } else if (focusTarget === 'password') {
            passwordRef.current?.focus();
        }
        setFocusTarget(null);
    }, [focusTarget]);

    function validateClient() {
        const next = { email: '', password: '' };
        const trimmedEmail = email.trim();

        if (!trimmedEmail) {
            next.email = 'Informe o e-mail.';
        } else if (!EMAIL_PATTERN.test(trimmedEmail)) {
            next.email = 'Informe um e-mail válido.';
        }

        if (!password) {
            next.password = 'Informe a senha.';
        }

        setFieldErrors(next);

        if (next.email) {
            setFocusTarget('email');
            return false;
        }

        if (next.password) {
            setFocusTarget('password');
            return false;
        }

        return true;
    }

    async function handleSubmit(event) {
        event.preventDefault();
        setFormError('');

        if (!validateClient()) {
            return;
        }

        setSubmitting(true);

        try {
            await login({ email: email.trim(), password });

            const from = location.state?.from;
            const target =
                from && typeof from.pathname === 'string' && from.pathname !== '/login'
                    ? `${from.pathname}${from.search ?? ''}${from.hash ?? ''}`
                    : '/dashboard';

            navigate(target, { replace: true });
        } catch (error) {
            const validation = getValidationErrors(error);

            if (validation) {
                const next = {
                    email: validation.email?.[0] ?? '',
                    password: validation.password?.[0] ?? '',
                };
                setFieldErrors(next);
                setFormError('');
                setFocusTarget(next.email ? 'email' : next.password ? 'password' : null);
                return;
            }

            if (isInvalidCredentialsError(error) || error.response?.status === 401) {
                setFormError('Credenciais inválidas.');
                setFocusTarget('email');
                toast.error('Credenciais inválidas.');
                return;
            }

            if (error.response?.status === 429) {
                setFormError('Muitas tentativas. Aguarde e tente novamente.');
                return;
            }

            const message = getErrorMessage(error);
            setFormError(message);
            toast.error(message);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <form className="flex flex-col gap-5" onSubmit={handleSubmit} noValidate>
            <div className="flex flex-col gap-2 text-left">
                <Label htmlFor={emailId}>E-mail</Label>
                <Input
                    ref={emailRef}
                    id={emailId}
                    name="email"
                    type="email"
                    autoComplete="username"
                    required
                    value={email}
                    invalid={Boolean(fieldErrors.email)}
                    disabled={submitting}
                    placeholder="admin@aura.local"
                    aria-describedby={fieldErrors.email ? `${emailId}-error` : undefined}
                    onChange={(event) => {
                        setEmail(event.target.value);
                        if (fieldErrors.email) {
                            setFieldErrors((prev) => ({ ...prev, email: '' }));
                        }
                    }}
                />
                {fieldErrors.email ? (
                    <p id={`${emailId}-error`} className="text-caption text-feedback-danger" role="alert">
                        {fieldErrors.email}
                    </p>
                ) : null}
            </div>

            <div className="flex flex-col gap-2 text-left">
                <Label htmlFor={passwordId}>Senha</Label>
                <div className="relative">
                    <Input
                        ref={passwordRef}
                        id={passwordId}
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        autoComplete="current-password"
                        required
                        value={password}
                        invalid={Boolean(fieldErrors.password)}
                        disabled={submitting}
                        placeholder="••••••••"
                        className="pr-11"
                        aria-describedby={fieldErrors.password ? `${passwordId}-error` : undefined}
                        onChange={(event) => {
                            setPassword(event.target.value);
                            if (fieldErrors.password) {
                                setFieldErrors((prev) => ({ ...prev, password: '' }));
                            }
                        }}
                    />
                    <button
                        type="button"
                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-ink-secondary hover:text-ink"
                        aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                        disabled={submitting}
                        onClick={() => setShowPassword((prev) => !prev)}
                    >
                        {showPassword ? <EyeOff size={18} strokeWidth={1.75} /> : <Eye size={18} strokeWidth={1.75} />}
                    </button>
                </div>
                {fieldErrors.password ? (
                    <p id={`${passwordId}-error`} className="text-caption text-feedback-danger" role="alert">
                        {fieldErrors.password}
                    </p>
                ) : null}
            </div>

            {formError ? (
                <p id={formErrorId} className="text-caption text-feedback-danger" role="alert">
                    {formError}
                </p>
            ) : null}

            <Button type="submit" className="w-full" loading={submitting} disabled={submitting}>
                Entrar
                {!submitting ? <ArrowRight size={16} strokeWidth={2} aria-hidden /> : null}
            </Button>
        </form>
    );
}
