import { useCallback, useEffect, useMemo, useState } from 'react';
import {
    Pencil,
    Plus,
    Power,
    PowerOff,
    RotateCcw,
} from 'lucide-react';
import ConfirmDeactivateUserDialog from '../components/admin/ConfirmDeactivateUserDialog';
import ConfirmResetUsageDialog from '../components/admin/ConfirmResetUsageDialog';
import UserFormModal from '../components/admin/UserFormModal';
import PageHeader from '../components/layout/PageHeader';
import Badge from '../components/ui/Badge';
import Button from '../components/ui/Button';
import EmptyState from '../components/ui/EmptyState';
import ErrorState from '../components/ui/ErrorState';
import Input from '../components/ui/Input';
import Pill from '../components/ui/Pill';
import Skeleton from '../components/ui/Skeleton';
import { useAuth } from '../hooks/useAuth';
import { useDocumentTitle } from '../hooks/useDocumentTitle';
import {
    useCreateUser,
    useDeactivateUser,
    useResetUserUsage,
    useUpdateUser,
} from '../hooks/useUserMutations';
import { useUsers } from '../hooks/useUsers';
import {
    ASSIGNABLE_ROLES,
    USER_ROLE_LABELS,
    formatQuota,
    formatUserDate,
} from '../lib/users';

const ROLE_FILTERS = [
    { id: '', label: 'Todos' },
    { id: 'admin', label: 'Admin' },
    ...ASSIGNABLE_ROLES.map((id) => ({ id, label: USER_ROLE_LABELS[id] })),
];

const ACTIVE_FILTERS = [
    { id: '', label: 'Qualquer status' },
    { id: '1', label: 'Ativos' },
    { id: '0', label: 'Inativos' },
];

/**
 * AdminUsersPage — gestão de usuários (PLAN_EXPANSAO §8.6).
 */
export default function AdminUsersPage() {
    useDocumentTitle('Usuários · Aura');

    const { user: authUser } = useAuth();

    const [roleFilter, setRoleFilter] = useState('');
    const [activeFilter, setActiveFilter] = useState('');
    const [qInput, setQInput] = useState('');
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);

    const [formState, setFormState] = useState(null);
    const [deactivateTarget, setDeactivateTarget] = useState(null);
    const [resetTarget, setResetTarget] = useState(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQ(qInput.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [qInput]);

    const filters = useMemo(() => {
        /** @type {Record<string, string|number>} */
        const params = { page, per_page: 20 };

        if (roleFilter) {
            params.role = roleFilter;
        }

        if (activeFilter !== '') {
            params.is_active = activeFilter;
        }

        if (q) {
            params.q = q;
        }

        return params;
    }, [roleFilter, activeFilter, q, page]);

    const users = useUsers(filters);

    const refresh = useCallback(async () => {
        await users.refetch();
    }, [users.refetch]);

    const createUser = useCreateUser({ onSuccess: refresh });
    const updateUser = useUpdateUser({ onSuccess: refresh });
    const deactivateUser = useDeactivateUser({ onSuccess: refresh });
    const resetUsage = useResetUserUsage({ onSuccess: refresh });

    const formOpen = formState !== null;
    const formMode = formState?.mode === 'edit' ? 'edit' : 'create';
    const formSubmitting =
        formMode === 'edit' ? updateUser.isLoading : createUser.isLoading;

    const rows = users.data;
    const meta = users.meta;

    return (
        <div className="flex flex-col gap-8 md:gap-10">
            <PageHeader
                title="Usuários"
                description="Gerencie papéis, ativação e cotas da equipe."
                actions={(
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setFormState({ mode: 'create' })}
                    >
                        <Plus size={16} strokeWidth={2} aria-hidden />
                        Novo usuário
                    </Button>
                )}
            />

            <section className="flex flex-col gap-3" aria-label="Filtros de usuários">
                <div className="aura-scroll-x flex flex-nowrap gap-2 pb-1">
                    {ROLE_FILTERS.map((option) => (
                        <Pill
                            key={option.id || 'all-roles'}
                            active={roleFilter === option.id}
                            onClick={() => {
                                setRoleFilter(option.id);
                                setPage(1);
                            }}
                        >
                            {option.label}
                        </Pill>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {ACTIVE_FILTERS.map((option) => (
                        <Pill
                            key={option.id || 'any-active'}
                            active={activeFilter === option.id}
                            onClick={() => {
                                setActiveFilter(option.id);
                                setPage(1);
                            }}
                        >
                            {option.label}
                        </Pill>
                    ))}
                    <Input
                        type="search"
                        value={qInput}
                        placeholder="Buscar nome ou e-mail"
                        aria-label="Buscar usuários"
                        className="min-w-[12rem] flex-1 sm:max-w-xs"
                        onChange={(event) => setQInput(event.target.value)}
                    />
                </div>
            </section>

            {users.status === 'error' ? (
                <ErrorState
                    title="Não foi possível carregar os usuários"
                    message={users.error || 'Tente novamente em instantes.'}
                    onRetry={users.refetch}
                />
            ) : null}

            {users.status === 'loading' && rows.length === 0 ? (
                <div aria-busy="true" aria-label="Carregando usuários">
                    <Skeleton.Table rows={6} />
                </div>
            ) : null}

            {users.status !== 'error' && users.status !== 'loading' && rows.length === 0 ? (
                <EmptyState
                    title="Nenhum usuário encontrado"
                    description="Ajuste os filtros ou crie o primeiro usuário da equipe."
                    action={{
                        label: 'Novo usuário',
                        onClick: () => setFormState({ mode: 'create' }),
                    }}
                />
            ) : null}

            {rows.length > 0 ? (
                <div className="overflow-x-auto rounded-xl border border-border-subtle bg-surface">
                    <table className="min-w-[48rem] w-full border-collapse text-body text-ink">
                        <thead>
                            <tr className="border-b border-border-subtle text-left text-caption font-medium text-ink-secondary">
                                <th className="px-4 py-3">Usuário</th>
                                <th className="px-4 py-3">Papel</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Uploads</th>
                                <th className="px-4 py-3">Criado em</th>
                                <th className="px-4 py-3 text-right">
                                    <span className="sr-only">Ações</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className={users.status === 'loading' ? 'opacity-60' : undefined}>
                            {rows.map((row) => {
                                const isAdminRow = row.role === 'admin';
                                const isSelf = authUser?.id === row.id;
                                const editable = !isAdminRow;

                                return (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border-subtle/60 transition hover:bg-surface-raised"
                                    >
                                        <td className="max-w-[14rem] px-4 py-3">
                                            <p className="truncate font-medium text-ink" title={row.name}>
                                                {row.name}
                                            </p>
                                            <p
                                                className="truncate text-caption text-ink-secondary"
                                                title={row.email}
                                            >
                                                {row.email}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={isAdminRow ? 'brand' : 'meta'}>
                                                {USER_ROLE_LABELS[row.role] ?? row.role}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={row.is_active ? 'positive' : 'danger'}>
                                                {row.is_active ? 'Ativo' : 'Inativo'}
                                            </Badge>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 tabular-nums text-ink-secondary">
                                            {formatQuota(
                                                row.usage?.uploads_used,
                                                row.limits?.max_uploads,
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-ink-secondary">
                                            {formatUserDate(row.created_at)}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2 text-right">
                                            {editable ? (
                                                <div className="flex items-center justify-end gap-1">
                                                    <IconAction
                                                        label={`Editar ${row.email}`}
                                                        onClick={() =>
                                                            setFormState({ mode: 'edit', user: row })
                                                        }
                                                    >
                                                        <Pencil size={16} strokeWidth={1.75} aria-hidden />
                                                    </IconAction>
                                                    <IconAction
                                                        label={`Reiniciar cotas de ${row.email}`}
                                                        onClick={() => setResetTarget(row)}
                                                    >
                                                        <RotateCcw size={16} strokeWidth={1.75} aria-hidden />
                                                    </IconAction>
                                                    {row.is_active ? (
                                                        <IconAction
                                                            label={`Desativar ${row.email}`}
                                                            tone="danger"
                                                            disabled={isSelf}
                                                            onClick={() => setDeactivateTarget(row)}
                                                        >
                                                            <PowerOff size={16} strokeWidth={1.75} aria-hidden />
                                                        </IconAction>
                                                    ) : (
                                                        <IconAction
                                                            label={`Reativar ${row.email}`}
                                                            onClick={() => {
                                                                void updateUser.mutate(row.id, {
                                                                    is_active: true,
                                                                });
                                                            }}
                                                        >
                                                            <Power size={16} strokeWidth={1.75} aria-hidden />
                                                        </IconAction>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-caption text-ink-muted">
                                                    Protegido
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            ) : null}

            {meta && meta.last_page > 1 ? (
                <div className="flex items-center justify-center gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={page <= 1 || users.status === 'loading'}
                        onClick={() => setPage((p) => Math.max(1, p - 1))}
                    >
                        Anterior
                    </Button>
                    <span className="text-caption text-ink-secondary">
                        Página {meta.current_page} de {meta.last_page}
                    </span>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={page >= meta.last_page || users.status === 'loading'}
                        onClick={() => setPage((p) => p + 1)}
                    >
                        Próxima
                    </Button>
                </div>
            ) : null}

            <UserFormModal
                open={formOpen}
                mode={formMode}
                user={formState?.user ?? null}
                submitting={formSubmitting}
                onClose={() => {
                    if (!formSubmitting) {
                        setFormState(null);
                    }
                }}
                onSubmit={async (payload) => {
                    if (formMode === 'edit' && formState?.user?.id != null) {
                        await updateUser.mutate(formState.user.id, payload);
                    } else {
                        await createUser.mutate(payload);
                    }
                    setFormState(null);
                }}
            />

            <ConfirmDeactivateUserDialog
                open={deactivateTarget !== null}
                user={deactivateTarget}
                submitting={deactivateUser.isLoading}
                onClose={() => {
                    if (!deactivateUser.isLoading) {
                        setDeactivateTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!deactivateTarget?.id) {
                        return;
                    }
                    await deactivateUser.mutate(deactivateTarget.id);
                    setDeactivateTarget(null);
                }}
            />

            <ConfirmResetUsageDialog
                open={resetTarget !== null}
                user={resetTarget}
                submitting={resetUsage.isLoading}
                onClose={() => {
                    if (!resetUsage.isLoading) {
                        setResetTarget(null);
                    }
                }}
                onConfirm={async () => {
                    if (!resetTarget?.id) {
                        return;
                    }
                    await resetUsage.mutate(resetTarget.id);
                    setResetTarget(null);
                }}
            />
        </div>
    );
}

function IconAction({ label, onClick, children, tone = 'default', disabled = false }) {
    return (
        <button
            type="button"
            disabled={disabled}
            className={[
                'inline-flex h-9 w-9 items-center justify-center rounded-full transition',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                'disabled:cursor-not-allowed disabled:opacity-40',
                tone === 'danger'
                    ? 'text-ink-secondary hover:bg-surface-raised hover:text-feedback-danger'
                    : 'text-ink-secondary hover:bg-surface-raised hover:text-ink',
            ].join(' ')}
            aria-label={label}
            title={label}
            onClick={onClick}
        >
            {children}
        </button>
    );
}
