import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Check, ChevronDown } from 'lucide-react';
import { cx } from '../../lib/cx';
import Pill from '../ui/Pill';

const POPOVER_WIDTH_PX = 280;

/**
 * Multi-select de cartões + checkbox “Incluir sem cartão”.
 *
 * @param {{
 *   items: Array<{ id: number, name: string }>,
 *   value: number[],
 *   includeUncarded?: boolean,
 *   onChange: (ids: number[]) => void,
 *   onIncludeUncardedChange: (value: boolean) => void,
 *   loading?: boolean,
 *   className?: string,
 * }} props
 */
export default function CreditCardMultiFilter({
    items = [],
    value = [],
    includeUncarded = false,
    onChange,
    onIncludeUncardedChange,
    loading = false,
    className = '',
}) {
    const popoverId = useId();
    const triggerRef = useRef(null);
    const popoverRef = useRef(null);
    const [open, setOpen] = useState(false);
    /** @type {[{ top: number, left: number }, Function]} */
    const [anchor, setAnchor] = useState({ top: 0, left: 0 });

    const selectedIds = Array.isArray(value) ? value : [];
    const isFiltered = selectedIds.length > 0;
    const label = !isFiltered
        ? 'Todos os cartões'
        : selectedIds.length === 1
            ? (items.find((item) => item.id === selectedIds[0])?.name ?? '1 cartão')
            : `${selectedIds.length} cartões`;

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
                triggerRef.current?.contains(target)
                || popoverRef.current?.contains(target)
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
                Cartões…
            </p>
        );
    }

    if (items.length === 0) {
        return null;
    }

    function toggleId(id) {
        if (selectedIds.includes(id)) {
            const next = selectedIds.filter((item) => item !== id);
            onChange(next);
            if (next.length === 0) {
                onIncludeUncardedChange(false);
            }
            return;
        }

        onChange([...selectedIds, id]);
    }

    function clearAll() {
        onChange([]);
        onIncludeUncardedChange(false);
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
                        aria-multiselectable="true"
                        aria-label="Cartões"
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
                                    onClick={clearAll}
                                >
                                    <span className="min-w-0 flex-1 truncate">Todos os cartões</span>
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
                                const active = selectedIds.includes(item.id);

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
                                            onClick={() => toggleId(item.id)}
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
                        {isFiltered ? (
                            <label className="flex cursor-pointer items-start gap-2 border-t border-border-subtle px-3 py-2.5 text-caption text-ink">
                                <input
                                    type="checkbox"
                                    className="mt-0.5 size-4 rounded border-border bg-surface-sunken text-brand focus-visible:ring-brand"
                                    checked={includeUncarded}
                                    onChange={(event) => {
                                        onIncludeUncardedChange(event.target.checked);
                                    }}
                                />
                                <span>Incluir sem cartão</span>
                            </label>
                        ) : null}
                    </div>,
                    document.body,
                )
                : null}
        </div>
    );
}
