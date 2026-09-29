import Pill from '../ui/Pill';
import { STATEMENT_KIND_OPTIONS } from '../../lib/statementKinds';

/**
 * StatementKindPills — Conta corrente | Fatura cartão (PLAN_EXPANSAO §8.4 / §9.3).
 *
 * @param {{
 *   value: 'checking'|'credit_card',
 *   onChange: (kind: 'checking'|'credit_card') => void,
 *   disabled?: boolean,
 *   allowCreditCard?: boolean,
 *   className?: string,
 * }} props
 */
export default function StatementKindPills({
    value = 'checking',
    onChange,
    disabled = false,
    allowCreditCard = true,
    className = '',
}) {
    const options = STATEMENT_KIND_OPTIONS.filter(
        (option) => option.id !== 'credit_card' || allowCreditCard,
    );

    return (
        <div
            className={['flex flex-wrap gap-2', className].filter(Boolean).join(' ')}
            role="group"
            aria-label="Tipo de extrato"
        >
            {options.map((option) => (
                <Pill
                    key={option.id}
                    active={value === option.id}
                    disabled={disabled}
                    onClick={() => {
                        if (!disabled) {
                            onChange(option.id);
                        }
                    }}
                >
                    {option.label}
                </Pill>
            ))}
        </div>
    );
}
