import { statementKindOption } from '../../lib/statementKinds';

/**
 * FileConstraintsHint — Etapa D §3.2 / PLAN_EXPANSAO §8.4.
 * Formatos aceitos, limite e link para CSV de exemplo conforme o tipo.
 *
 * @param {{
 *   kind?: 'checking'|'credit_card',
 *   className?: string,
 * }} props
 */
export default function FileConstraintsHint({ kind = 'checking', className = '' }) {
    const option = statementKindOption(kind);

    return (
        <div
            className={[
                'flex flex-col gap-1.5 text-caption font-normal text-ink-muted',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <p>{option.help}</p>
            <p>
                {option.formats}
                {' · '}
                <a
                    href={option.sampleHref}
                    download
                    className="font-medium text-ink-secondary underline-offset-2 transition hover:text-ink hover:underline"
                >
                    {option.sampleLabel}
                </a>
            </p>
        </div>
    );
}
