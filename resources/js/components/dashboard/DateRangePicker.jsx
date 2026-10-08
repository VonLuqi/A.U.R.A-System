import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { CalendarRange, ChevronLeft, ChevronRight } from 'lucide-react';
import { DayPicker } from 'react-day-picker';
import { ptBR } from 'react-day-picker/locale';
import { toast } from 'sonner';
import {
    PERIOD_PRESET_IDS,
    PERIOD_PRESETS,
    dateRangeLimitMessage,
    daysBetween,
    formatIsoDate,
    formatRangeLabel,
    isCyclePreset,
    isWithinDateRangeLimit,
    parseIsoDate,
} from '../../lib/dates';
import { TOAST_DURATION } from '../../lib/toast';
import { cx } from '../../lib/cx';
import Button from '../ui/Button';
import DateInput from '../ui/DateInput';
import Pill from '../ui/Pill';
import 'react-day-picker/style.css';

const NAMED_OPTIONS = [
    { id: PERIOD_PRESET_IDS.current_month, label: 'Este mês' },
    { id: PERIOD_PRESET_IDS.last_30, label: '30 dias' },
    { id: PERIOD_PRESET_IDS.last_90, label: '90 dias' },
    { id: PERIOD_PRESET_IDS.my_cycle, label: 'Meu ciclo' },
    { id: PERIOD_PRESET_IDS.all, label: 'Todo o histórico' },
];

const POPOVER_WIDTH_PX = 352;

/**
 * DateRangePicker — presets + custom (react-day-picker) · PLAN_EXPANSAO §8.3.
 *
 * Popover via portal (fixed) para não ser clipado por `aura-scroll-x` no FilterBar.
 * Início/fim editáveis por input + calendário (2 cliques: início → fim).
 *
 * @param {{
 *   preset: string,
 *   from: string,
 *   to: string,
 *   cycleOffset?: number,
 *   expenseCycleDay?: number,
 *   incomeCycleDay?: number,
 *   maxDays?: number|null,
 *   onPresetChange: (presetId: string) => void,
 *   onCycleOffsetChange?: (offset: number) => void,
 *   onCycleDaysChange?: (days: { expense_cycle_day: number, income_cycle_day: number }) => void,
 *   onCustomRange: (range: { from: string, to: string }) => void,
 *   className?: string,
 * }} props
 */
export default function DateRangePicker({
    preset,
    from,
    to,
    cycleOffset = 0,
    expenseCycleDay = 6,
    incomeCycleDay = 12,
    maxDays = null,
    onPresetChange,
    onCycleOffsetChange,
    onCycleDaysChange,
    onCustomRange,
    className = '',
}) {
    const popoverId = useId();
    const rootRef = useRef(null);
    const customBtnRef = useRef(null);
    const popoverRef = useRef(null);
    const [open, setOpen] = useState(false);
    /** @type {[{ from?: Date, to?: Date }, Function]} */
    const [draft, setDraft] = useState({ from: undefined, to: undefined });
    /** @type {[Date, Function]} */
    const [month, setMonth] = useState(() => new Date());
    /** @type {[{ top: number, left: number }, Function]} */
    const [anchor, setAnchor] = useState({ top: 0, left: 0 });

    const isCustom = preset === PERIOD_PRESET_IDS.custom;
    const cycleActive = isCyclePreset(preset);
    const [draftExpenseDay, setDraftExpenseDay] = useState(String(expenseCycleDay));
    const [draftIncomeDay, setDraftIncomeDay] = useState(String(incomeCycleDay));
    const limitTitle =
        maxDays != null
            ? `Máximo de ${maxDays} dias para o seu perfil`
            : undefined;

    useEffect(() => {
        setDraftExpenseDay(String(expenseCycleDay));
        setDraftIncomeDay(String(incomeCycleDay));
    }, [expenseCycleDay, incomeCycleDay]);

    function commitCycleDays(nextExpense, nextIncome) {
        if (!onCycleDaysChange) {
            return;
        }

        const expense = Math.max(1, Math.min(31, Number.parseInt(String(nextExpense), 10) || 1));
        const income = Math.max(1, Math.min(31, Number.parseInt(String(nextIncome), 10) || 1));
        setDraftExpenseDay(String(expense));
        setDraftIncomeDay(String(income));

        if (expense === expenseCycleDay && income === incomeCycleDay) {
            return;
        }

        onCycleDaysChange({
            expense_cycle_day: expense,
            income_cycle_day: income,
        });
    }

    useLayoutEffect(() => {
        if (!open) {
            return undefined;
        }

        function updateAnchor() {
            const btn = customBtnRef.current;
            if (!btn) {
                return;
            }

            const rect = btn.getBoundingClientRect();
            const maxLeft = Math.max(8, window.innerWidth - POPOVER_WIDTH_PX - 8);
            setAnchor({
                top: rect.bottom + 8,
                left: Math.min(Math.max(8, rect.left), maxLeft),
            });
        }

        updateAnchor();
        window.addEventListener('resize', updateAnchor);
        window.addEventListener('scroll', updateAnchor, true);

        return () => {
            window.removeEventListener('resize', updateAnchor);
            window.removeEventListener('scroll', updateAnchor, true);
        };
    }, [open]);

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const fromDate = parseIsoDate(from) ?? undefined;
        const toDate = parseIsoDate(to) ?? undefined;
        setDraft({ from: fromDate, to: toDate });
        setMonth(fromDate ?? toDate ?? new Date());

        function onPointerDown(event) {
            const target = event.target;
            if (
                rootRef.current?.contains(target) ||
                popoverRef.current?.contains(target)
            ) {
                return;
            }
            setOpen(false);
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                setOpen(false);
                customBtnRef.current?.focus();
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open, from, to]);

    function notifyLimit(requestedDays) {
        toast.error(dateRangeLimitMessage(maxDays, requestedDays), {
            duration: TOAST_DURATION,
        });
    }

    function handleNamedPreset(presetId) {
        if (presetId === PERIOD_PRESET_IDS.all) {
            if (maxDays != null) {
                toast.error('Todo o histórico não está disponível para o seu perfil.', {
                    duration: TOAST_DURATION,
                });
                return;
            }

            setOpen(false);
            onPresetChange(presetId);
            return;
        }

        if (isCyclePreset(presetId)) {
            setOpen(false);
            onPresetChange(presetId);
            return;
        }

        const range = PERIOD_PRESETS[presetId]?.();

        if (!range) {
            return;
        }

        const requested = daysBetween(range.from, range.to);

        if (!isWithinDateRangeLimit(range.from, range.to, maxDays)) {
            notifyLimit(requested);
            return;
        }

        setOpen(false);
        onPresetChange(presetId);
    }

    function handleCustomToggle() {
        setOpen((prev) => !prev);
    }

    /**
     * Calendário: com intervalo completo, o próximo clique redefine o início
     * (evita ficar “preso” só movendo o fim).
     *
     * @param {{ from?: Date, to?: Date }|undefined} next
     * @param {Date} triggerDate
     */
    function handleCalendarSelect(next, triggerDate) {
        if (draft.from && draft.to && triggerDate) {
            setDraft({ from: startOfLocalDay(triggerDate), to: undefined });
            setMonth(startOfLocalDay(triggerDate));
            return;
        }

        setDraft(next ?? { from: undefined, to: undefined });
        if (triggerDate) {
            setMonth(startOfLocalDay(triggerDate));
        }
    }

    /**
     * @param {'from'|'to'} field
     * @param {string} iso
     */
    function handleInputChange(field, iso) {
        const parsed = parseIsoDate(iso);

        if (!iso) {
            setDraft((prev) => ({ ...prev, [field]: undefined }));
            return;
        }

        if (!parsed) {
            return;
        }

        setDraft((prev) => {
            const next = { ...prev, [field]: parsed };

            if (field === 'from' && next.to && formatIsoDate(parsed) > formatIsoDate(next.to)) {
                next.to = parsed;
            }

            if (field === 'to' && next.from && formatIsoDate(parsed) < formatIsoDate(next.from)) {
                next.from = parsed;
            }

            return next;
        });
        setMonth(parsed);
    }

    function handleApply() {
        if (!draft.from || !draft.to) {
            toast.error('Selecione a data inicial e a data final.', {
                duration: TOAST_DURATION,
            });
            return;
        }

        let fromIso = formatIsoDate(draft.from);
        let toIso = formatIsoDate(draft.to);

        if (fromIso > toIso) {
            const swap = fromIso;
            fromIso = toIso;
            toIso = swap;
        }

        const requested = daysBetween(fromIso, toIso);

        if (!isWithinDateRangeLimit(fromIso, toIso, maxDays)) {
            notifyLimit(requested);
            return;
        }

        onCustomRange({ from: fromIso, to: toIso });
        setOpen(false);
    }

    const customLabel =
        isCustom && from && to
            ? formatRangeLabel(from, to)
            : 'Personalizado';

    const disabledMatcher =
        maxDays != null && draft.from && !draft.to
            ? (date) => {
                  const start = startOfLocalDay(draft.from);
                  const day = startOfLocalDay(date);
                  const diff =
                      Math.abs(Math.round((day.getTime() - start.getTime()) / 86400000)) + 1;

                  return diff > maxDays;
              }
            : undefined;

    const fromIsoValue = draft.from ? formatIsoDate(draft.from) : '';
    const toIsoValue = draft.to ? formatIsoDate(draft.to) : '';

    const popover =
        open && typeof document !== 'undefined'
            ? createPortal(
                  <div
                      ref={popoverRef}
                      id={popoverId}
                      role="dialog"
                      aria-label="Escolher intervalo de datas"
                      style={{ top: anchor.top, left: anchor.left }}
                      className={cx(
                          'fixed z-[110] w-[min(100vw-1rem,22rem)] rounded-2xl border border-border bg-surface p-4 shadow-none',
                      )}
                  >
                      <div className="mb-3 grid grid-cols-2 gap-2">
                          <label className="flex min-w-0 flex-col gap-1">
                              <span className="text-caption font-medium text-ink-secondary">
                                  Início
                              </span>
                              <DateInput
                                  value={fromIsoValue}
                                  onChange={(event) =>
                                      handleInputChange('from', event.target.value)
                                  }
                                  className="h-10 text-caption"
                              />
                          </label>
                          <label className="flex min-w-0 flex-col gap-1">
                              <span className="text-caption font-medium text-ink-secondary">
                                  Fim
                              </span>
                              <DateInput
                                  value={toIsoValue}
                                  onChange={(event) =>
                                      handleInputChange('to', event.target.value)
                                  }
                                  className="h-10 text-caption"
                              />
                          </label>
                      </div>

                      <p className="mb-2 text-small text-ink-muted">
                          {draft.from && !draft.to
                              ? 'Agora escolha a data final no calendário.'
                              : 'Clique na data inicial e depois na final — ou use os campos acima.'}
                      </p>

                      <DayPicker
                          mode="range"
                          locale={ptBR}
                          selected={draft}
                          month={month}
                          onMonthChange={setMonth}
                          onSelect={handleCalendarSelect}
                          disabled={disabledMatcher}
                          className="aura-day-picker"
                          numberOfMonths={1}
                      />

                      {maxDays != null ? (
                          <p className="mt-2 text-small text-ink-muted">
                              Limite do perfil: {maxDays} dias.
                          </p>
                      ) : null}

                      <div className="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-border-subtle pt-3">
                          <Button
                              type="button"
                              variant="secondary"
                              size="sm"
                              onClick={() => setOpen(false)}
                          >
                              Cancelar
                          </Button>
                          <Button type="button" size="sm" onClick={handleApply}>
                              Aplicar
                          </Button>
                      </div>
                  </div>,
                  document.body,
              )
            : null;

    return (
        <div
            ref={rootRef}
            className={cx('relative flex items-center gap-2', className || 'flex-nowrap')}
            role="group"
            aria-label="Período"
        >
            {NAMED_OPTIONS.filter(
                (option) => option.id !== PERIOD_PRESET_IDS.all || maxDays == null,
            ).map((option) => {
                const range = PERIOD_PRESETS[option.id]?.();
                const exceeds =
                    range
                    && option.id !== PERIOD_PRESET_IDS.all
                    && maxDays != null
                    && !isWithinDateRangeLimit(range.from, range.to, maxDays);

                return (
                    <Pill
                        key={option.id}
                        active={preset === option.id}
                        title={exceeds ? limitTitle : undefined}
                        aria-disabled={exceeds || undefined}
                        className={exceeds ? 'opacity-60' : undefined}
                        onClick={() => handleNamedPreset(option.id)}
                    >
                        {option.label}
                    </Pill>
                );
            })}

            {cycleActive && onCycleOffsetChange ? (
                <div
                    className="flex items-center gap-0.5 rounded-full border border-border-subtle bg-surface-sunken/60 px-0.5"
                    role="group"
                    aria-label="Navegar entre ciclos"
                >
                    <button
                        type="button"
                        className={cx(
                            'inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary transition',
                            'hover:bg-surface-raised hover:text-ink',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                            'disabled:cursor-not-allowed disabled:opacity-40',
                        )}
                        aria-label="Ciclo anterior"
                        disabled={cycleOffset <= -120}
                        onClick={() => onCycleOffsetChange(cycleOffset - 1)}
                    >
                        <ChevronLeft size={16} strokeWidth={1.75} aria-hidden />
                    </button>
                    <span
                        className="min-w-[9.5rem] px-1 text-center text-caption font-medium tabular-nums text-ink"
                        title="Intervalo do ciclo selecionado"
                    >
                        {from && to ? formatRangeLabel(from, to) : '—'}
                    </span>
                    <button
                        type="button"
                        className={cx(
                            'inline-flex h-9 w-9 items-center justify-center rounded-full text-ink-secondary transition',
                            'hover:bg-surface-raised hover:text-ink',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                            'disabled:cursor-not-allowed disabled:opacity-40',
                        )}
                        aria-label="Próximo ciclo"
                        disabled={cycleOffset >= 120}
                        onClick={() => onCycleOffsetChange(cycleOffset + 1)}
                    >
                        <ChevronRight size={16} strokeWidth={1.75} aria-hidden />
                    </button>
                    {cycleOffset !== 0 ? (
                        <button
                            type="button"
                            className={cx(
                                'mr-1 h-8 shrink-0 rounded-full px-2.5 text-caption font-medium text-ink-secondary transition',
                                'hover:bg-surface-raised hover:text-ink',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                            )}
                            onClick={() => onCycleOffsetChange(0)}
                        >
                            Atual
                        </button>
                    ) : null}
                </div>
            ) : null}

            {cycleActive && onCycleDaysChange ? (
                <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Dias do ciclo">
                    <label className="flex items-center gap-1.5 text-caption text-ink-secondary">
                        <span className="shrink-0">Saídas</span>
                        <input
                            type="number"
                            min={1}
                            max={31}
                            value={draftExpenseDay}
                            aria-label="Dia do ciclo de saídas"
                            className={cx(
                                'h-9 w-14 rounded-lg border border-border bg-surface-sunken px-2 text-body text-ink',
                                'outline-none focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
                            )}
                            onChange={(event) => setDraftExpenseDay(event.target.value)}
                            onBlur={() => commitCycleDays(draftExpenseDay, draftIncomeDay)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.currentTarget.blur();
                                }
                            }}
                        />
                    </label>
                    <label className="flex items-center gap-1.5 text-caption text-ink-secondary">
                        <span className="shrink-0">Entradas</span>
                        <input
                            type="number"
                            min={1}
                            max={31}
                            value={draftIncomeDay}
                            aria-label="Dia do ciclo de entradas"
                            className={cx(
                                'h-9 w-14 rounded-lg border border-border bg-surface-sunken px-2 text-body text-ink',
                                'outline-none focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
                            )}
                            onChange={(event) => setDraftIncomeDay(event.target.value)}
                            onBlur={() => commitCycleDays(draftExpenseDay, draftIncomeDay)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.currentTarget.blur();
                                }
                            }}
                        />
                    </label>
                </div>
            ) : null}

            <Pill
                ref={customBtnRef}
                active={isCustom || open}
                title={limitTitle}
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-controls={open ? popoverId : undefined}
                onClick={handleCustomToggle}
                className="max-w-[14rem] gap-1.5"
            >
                <CalendarRange size={14} strokeWidth={1.75} aria-hidden className="shrink-0" />
                <span className="truncate">{customLabel}</span>
            </Pill>

            {popover}
        </div>
    );
}

/**
 * @param {Date} date
 * @returns {Date}
 */
function startOfLocalDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}
