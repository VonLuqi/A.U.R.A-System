import CategorySelect from './CategorySelect';
import DateRangePicker from './DateRangePicker';
import SearchField from './SearchField';
import TypePills from './TypePills';
import { cx } from '../../lib/cx';

/**
 * FilterBar — Etapa D §4.5.5 / §5.3.1 / §6.2 / PLAN_EXPANSAO §8.3.
 * Wrap responsivo; faixa de pills com scroll-X sutil no mobile.
 */
export default function FilterBar({
    periodPreset,
    from,
    to,
    maxDateRangeDays = null,
    onPeriodChange,
    onCustomRange,
    type,
    onTypeChange,
    categoryId,
    onCategoryChange,
    categories = [],
    categoriesLoading = false,
    q,
    onSearchChange,
    refreshing = false,
    className = '',
}) {
    return (
        <section
            className={cx('flex flex-col gap-3', className)}
            aria-label="Filtros do dashboard"
        >
            <div className="aura-scroll-x flex flex-nowrap items-center gap-x-3 gap-y-2 pb-1">
                <DateRangePicker
                    preset={periodPreset}
                    from={from}
                    to={to}
                    maxDays={maxDateRangeDays}
                    onPresetChange={onPeriodChange}
                    onCustomRange={onCustomRange}
                    className="shrink-0 flex-nowrap"
                />
                <TypePills
                    value={type}
                    onChange={onTypeChange}
                    className="shrink-0 flex-nowrap"
                />
                {refreshing ? (
                    <span className="shrink-0 text-small text-ink-muted" aria-live="polite">
                        Atualizando…
                    </span>
                ) : null}
            </div>

            <div className="flex flex-wrap items-start gap-x-3 gap-y-2">
                <CategorySelect
                    categories={categories}
                    value={categoryId}
                    loading={categoriesLoading}
                    onChange={onCategoryChange}
                    className="min-w-0 flex-1"
                />
                <SearchField
                    value={q}
                    onChange={onSearchChange}
                    className="min-w-[12rem] flex-1 sm:max-w-sm sm:flex-none"
                />
            </div>
        </section>
    );
}
