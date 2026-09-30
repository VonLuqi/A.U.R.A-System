import { forwardRef, useEffect, useId, useRef, useState } from 'react';
import { Calendar } from 'lucide-react';
import {
    formatBrDate,
    maskBrDateInput,
    parseBrDate,
    parseIsoDate,
} from '../../lib/dates';
import { cx } from '../../lib/cx';
import Input from './Input';

/**
 * DateInput — exibe dd/mm/aaaa; value/onChange em YYYY-MM-DD (contrato API).
 * O picker nativo fica oculto (locale do SO); o campo visível é sempre pt-BR.
 *
 * @type {import('react').ForwardRefExoticComponent<{
 *   id?: string,
 *   value?: string,
 *   disabled?: boolean,
 *   invalid?: boolean,
 *   className?: string,
 *   placeholder?: string,
 *   onChange?: (event: { target: { value: string } }) => void,
 *   onBlur?: (event: import('react').FocusEvent<HTMLInputElement>) => void,
 * } & import('react').RefAttributes<HTMLInputElement>>}
 */
const DateInput = forwardRef(function DateInput(
    {
        id,
        value = '',
        disabled = false,
        invalid = false,
        className = '',
        placeholder = 'dd/mm/aaaa',
        onChange,
        onBlur,
        ...props
    },
    ref,
) {
    const autoId = useId();
    const inputId = id ?? autoId;
    const pickerRef = useRef(null);
    const [text, setText] = useState(() => formatBrDate(value));
    const [focused, setFocused] = useState(false);

    useEffect(() => {
        if (focused) {
            return;
        }

        setText(formatBrDate(value));
    }, [value, focused]);

    function emitIso(iso) {
        onChange?.({ target: { value: iso } });
    }

    function commitText(raw) {
        const trimmed = String(raw ?? '').trim();

        if (!trimmed) {
            setText('');
            emitIso('');
            return;
        }

        const iso = parseBrDate(trimmed);

        if (iso) {
            setText(formatBrDate(iso));
            emitIso(iso);
            return;
        }

        setText(formatBrDate(value));
    }

    return (
        <div className="relative">
            <Input
                ref={ref}
                id={inputId}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                placeholder={placeholder}
                value={text}
                disabled={disabled}
                invalid={invalid}
                className={cx('pr-11', className)}
                aria-describedby={`${inputId}-hint`}
                onFocus={() => setFocused(true)}
                onBlur={(event) => {
                    setFocused(false);
                    commitText(event.target.value);
                    onBlur?.(event);
                }}
                onChange={(event) => {
                    const next = maskBrDateInput(event.target.value);
                    setText(next);

                    const iso = parseBrDate(next);
                    if (iso) {
                        emitIso(iso);
                    } else if (!next.trim()) {
                        emitIso('');
                    }
                }}
                {...props}
            />
            <span id={`${inputId}-hint`} className="sr-only">
                Formato dia/mês/ano
            </span>
            <input
                ref={pickerRef}
                type="date"
                tabIndex={-1}
                aria-hidden
                disabled={disabled}
                value={parseIsoDate(value) ? value : ''}
                className="pointer-events-none absolute h-0 w-0 opacity-0"
                onChange={(event) => {
                    const iso = event.target.value;
                    setText(formatBrDate(iso));
                    emitIso(iso);
                }}
            />
            <button
                type="button"
                tabIndex={-1}
                disabled={disabled}
                aria-label="Abrir calendário"
                className={cx(
                    'absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-lg',
                    'text-ink-muted transition hover:text-ink',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                )}
                onClick={() => {
                    const el = pickerRef.current;
                    if (!el || disabled) {
                        return;
                    }
                    if (typeof el.showPicker === 'function') {
                        try {
                            el.showPicker();
                            return;
                        } catch {
                            // fall through
                        }
                    }
                    el.focus();
                    el.click();
                }}
            >
                <Calendar size={16} strokeWidth={1.75} aria-hidden />
            </button>
        </div>
    );
});

export default DateInput;
