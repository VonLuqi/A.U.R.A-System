import { PERIOD_PRESET_IDS } from '../../lib/dates';
import Pill from '../ui/Pill';

const PERIOD_OPTIONS = [
    { id: PERIOD_PRESET_IDS.current_month, label: 'Este mês' },
    { id: PERIOD_PRESET_IDS.last_30, label: '30 dias' },
    { id: PERIOD_PRESET_IDS.last_90, label: '90 dias' },
];

/**
 * PeriodPills — Etapa D §4.5.1.
 *
 * @param {{
 *   value: string,
 *   onChange: (presetId: string) => void,
 *   className?: string,
 * }} props
 */
export default function PeriodPills({ value, onChange, className = '' }) {
    return (
        <div
            className={['flex flex-nowrap gap-2', className].filter(Boolean).join(' ')}
            role="group"
            aria-label="Período"
        >
            {PERIOD_OPTIONS.map((option) => (
                <Pill
                    key={option.id}
                    active={value === option.id}
                    onClick={() => onChange(option.id)}
                >
                    {option.label}
                </Pill>
            ))}
        </div>
    );
}
