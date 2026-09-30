import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Check, ChevronDown } from 'lucide-react';
import { cx } from '../../lib/cx';
import Pill from '../ui/Pill';

const POPOVER_WIDTH_PX = 280;

/**
 * FilterSelect — select compacto (pill + popover), padrão do CategorySelect.
 *
 * @param {{
 *   items: Array<{ id: number, name: string }>,
 *   value: '' | number,
 *   onChange: (id: '' | number) => void,
 *   allLabel: string,
 *   ariaLabel: string,
 *   loading?: boolean,
 *   loadingLabel?: string,
 *   className?: string,
 * }} props
 */
export default function FilterSelect({
    items = [],
    value = '',
    onChange,
    allLabel,
    ariaLabel,
    loading = false,
    loadingLabel = 'Carregando…',
    className = '',
}) {
    const popoverId = useId();
    const triggerRef = useRef(null);
    const popoverRef = useRef(null);
    const [open, setOpen] = useState(false);
    /** @type {[{ top: number, left: number }, Function]} */
    const [anchor, setAnchor] = useState({ top: 0, left: 0 });

    const selected = items.find((item) => item.id === value);
    const label = selected?.name ?? allLabel;
    const isFiltered = value !== '' && value != null;

    useLayoutEffect(() => {
        if (!open) {
            return undefined;
        }

        function updateAnchor() {
            const el = triggerRef.current;
            if (!el) {
                return;
            }
            const rect = el.getBoundingClientRect();
            const left = Math.min(
                Math.max(8, rect.left),
                Math.max(8, window.innerWidth - POPOVER_WIDTH_PX - 8),
            );
            setAnchor({ top: rect.bottom + 8, left });
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

        function onPointerDown(event) {
            const target = event.target;
            if (
                triggerRef.current?.contains(target) ||
                popoverRef.current?.contains(target)
            ) {
                return;
            }
            setOpen(false);
        }

        function onKeyDown(event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        }

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    if (loading && items.length === 0) {
        return (
            <p className={cx('shrink-0 text-caption text-ink-muted', className)}>
                {loadingLabel}
            </p>
        );
    }

    if (items.length === 0) {
        return null;
    }

    function selectItem(next) {
        onChange(next);
        setOpen(false);
    }

    return (
        <div className={cx('relative shrink-0', className)}>
            <Pill
                ref={triggerRef}
                active={isFiltered || open}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={open ? popoverId : undefined}
                onClick={() => setOpen((prev) => !prev)}
                className="max-w-[14rem] gap-2"
            >
                <span className="truncate">{label}</span>
                <ChevronDown
                    size={14}
                    strokeWidth={2}
                    aria-hidden
                    className={cx('shrink-0 opacity-70 transition', open && 'rotate-180')}
                />
            </Pill>

            {open
                ? createPortal(
                      <div
                          ref={popoverRef}
                          id={popoverId}
                          role="listbox"
                          aria-label={ariaLabel}
                          className="fixed z-[110] overflow-hidden rounded-xl border border-border bg-surface shadow-lg"
                          style={{
                              top: anchor.top,
                              left: anchor.left,
                              width: POPOVER_WIDTH_PX,
                          }}
                      >
                          <ul className="max-h-72 overflow-y-auto py-1">
                              <li>
                                  <button
                                      type="button"
                                      role="option"
                                      aria-selected={!isFiltered}
                                      className={cx(
                                          'flex w-full items-center gap-2 px-3 py-2.5 text-left text-caption transition',
                                          !isFiltered
                                              ? 'bg-surface-raised font-semibold text-ink'
                                              : 'text-ink hover:bg-surface-raised',
                                      )}
                                      onClick={() => selectItem('')}
                                  >
                                      <span className="min-w-0 flex-1 truncate">{allLabel}</span>
                                      {!isFiltered ? (
                                          <Check
                                              size={16}
                                              strokeWidth={2}
                                              className="shrink-0 text-brand"
                                              aria-hidden
                                          />
                                      ) : null}
                                  </button>
                              </li>
                              {items.map((item) => {
                                  const active = value === item.id;

                                  return (
                                      <li key={item.id}>
                                          <button
                                              type="button"
                                              role="option"
                                              aria-selected={active}
                                              className={cx(
                                                  'flex w-full items-center gap-2 px-3 py-2.5 text-left text-caption transition',
                                                  active
                                                      ? 'bg-surface-raised font-semibold text-ink'
                                                      : 'text-ink hover:bg-surface-raised',
                                              )}
                                              onClick={() => selectItem(item.id)}
                                          >
                                              <span className="min-w-0 flex-1 truncate">
                                                  {item.name}
                                              </span>
                                              {active ? (
                                                  <Check
                                                      size={16}
                                                      strokeWidth={2}
                                                      className="shrink-0 text-brand"
                                                      aria-hidden
                                                  />
                                              ) : null}
                                          </button>
                                      </li>
                                  );
                              })}
                          </ul>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}
