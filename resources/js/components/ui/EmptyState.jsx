import { Inbox } from 'lucide-react';
import { Link } from 'react-router-dom';
import Button from './Button';

/**
 * Mensagens canônicas — Etapa D §5.3.2.
 */
export const EMPTY_COPY = {
    period: {
        title: 'Nenhum dado neste período',
        description: 'Ajuste o período ou importe um extrato.',
    },
    account: {
        title: 'Comece importando seu extrato do Nubank.',
        description: 'Ainda não há movimentações nesta conta.',
        action: { label: 'Importar extrato', to: '/upload' },
    },
    search: {
        title: 'Nenhum resultado para essa busca.',
        description: 'Tente outro termo ou limpe a busca.',
    },
    filters: {
        title: 'Nenhum resultado para os filtros.',
        description: 'Tente limpar a busca, o tipo ou a categoria.',
    },
    chart: {
        title: 'Sem série para exibir.',
        description: 'Não há movimentações neste período para o gráfico.',
    },
};

const ACTION_LINK_CLASS = {
    primary:
        'inline-flex h-9 items-center justify-center rounded-full bg-brand px-4 text-caption font-semibold text-ink-on-brand transition hover:brightness-95',
    secondary:
        'inline-flex h-9 items-center justify-center rounded-full border border-border px-4 text-caption font-medium text-ink transition hover:bg-surface-raised',
};

/**
 * EmptyState — Etapa D §5.3.2.
 *
 * @param {{
 *   title: string,
 *   description?: string,
 *   action?: {
 *     label: string,
 *     to?: string,
 *     onClick?: () => void,
 *     variant?: 'primary'|'secondary',
 *   },
 *   icon?: import('react').ReactNode | false,
 *   className?: string,
 *   children?: import('react').ReactNode,
 * }} props
 */
export default function EmptyState({
    title,
    description,
    action = null,
    icon,
    className = '',
    children,
}) {
    const showDefaultIcon = icon === undefined;
    const resolvedIcon = showDefaultIcon ? (
        <Inbox size={28} strokeWidth={1.5} className="text-ink-muted" aria-hidden />
    ) : icon;

    const actionVariant = action?.variant ?? 'primary';

    return (
        <div
            className={[
                'flex flex-col items-center justify-center gap-2 px-4 py-10 text-center',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
            role="status"
        >
            {resolvedIcon ? <div className="mb-1">{resolvedIcon}</div> : null}
            <p className="text-body-lg font-semibold text-ink">{title}</p>
            {description ? (
                <p className="max-w-sm text-caption text-ink-secondary">{description}</p>
            ) : null}
            {action?.label ? (
                <div className="mt-3">
                    {action.to ? (
                        <Link
                            to={action.to}
                            className={ACTION_LINK_CLASS[actionVariant] ?? ACTION_LINK_CLASS.primary}
                        >
                            {action.label}
                        </Link>
                    ) : (
                        <Button
                            type="button"
                            variant={actionVariant}
                            size="sm"
                            onClick={action.onClick}
                        >
                            {action.label}
                        </Button>
                    )}
                </div>
            ) : null}
            {children}
        </div>
    );
}
