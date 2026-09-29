import Pill from '../ui/Pill';

const TYPE_OPTIONS = [
    { id: '', label: 'Todos' },
    { id: 'credit', label: 'Entradas' },
    { id: 'debit', label: 'Saídas' },
];

/**
 * TypePills — Etapa D §4.5.2.
 *
 * @param {{
 *   value: '' | 'credit' | 'debit',
 *   onChange: (type: '' | 'credit' | 'debit') => void,
 *   className?: string,
 * }} props
 */
export default function TypePills({ value = '', onChange, className = '' }) {
    return (
        <div
            className={['flex flex-nowrap gap-2', className].filter(Boolean).join(' ')}
            role="group"
            aria-label="Tipo de movimentação"
        >
            {TYPE_OPTIONS.map((option) => (
                <Pill
                    key={option.id || 'all'}
                    active={value === option.id}
                    onClick={() => onChange(option.id)}
                >
                    {option.label}
                </Pill>
            ))}
        </div>
    );
}
