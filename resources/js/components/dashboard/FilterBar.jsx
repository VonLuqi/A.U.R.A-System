import { useMemo, useState } from 'react';
import { ListFilter } from 'lucide-react';
import {
    PERIOD_PRESET_IDS,
    PERIOD_PRESET_LABELS,
    formatRangeLabel,
} from '../../lib/dates';
import { cx } from '../../lib/cx';
import Button from '../ui/Button';
import Modal from '../ui/Modal';
import CategorySelect from './CategorySelect';
import DateRangePicker from './DateRangePicker';
import FilterSelect from './FilterSelect';
import SearchField from './SearchField';
import TypePills from './TypePills';

const TYPE_LABELS = {
    '': 'Todos',
    credit: 'Entradas',
    debit: 'Saídas',
};

/**
 * FilterBar — desktop: faixa inline; mobile: busca + ícone que abre modal.
 */
export default function FilterBar({
    periodPreset,
    from,
    to,
    cycleOffset = 0,
    maxDateRangeDays = null,
    onPeriodChange,
    onCycleOffsetChange,
    onCustomRange,
    type,
    onTypeChange,
    categoryId,
    onCategoryChange,
    categories = [],
    categoriesLoading = false,
    creditCardId = '',
    onCreditCardChange,
    creditCards = [],
    creditCardsLoading = false,
    showCreditCardFilter = false,
    debtorId = '',
    onDebtorChange,
    debtors = [],
    debtorsLoading = false,
    showDebtorFilter = false,
    q,
    onSearchChange,
    refreshing = false,
    className = '',
}) {
    const [filtersOpen, setFiltersOpen] = useState(false);

    const selectedCategory = categories.find((c) => c.id === categoryId);
    const selectedCard = creditCards.find((c) => c.id === creditCardId);
    const selectedDebtor = debtors.find((d) => d.id === debtorId);
    const periodLabel =
        periodPreset === PERIOD_PRESET_IDS.custom
        || periodPreset === PERIOD_PRESET_IDS.my_cycle
        || periodPreset === PERIOD_PRESET_IDS.card_cycle
            ? formatRangeLabel(from, to)
                || PERIOD_PRESET_LABELS[periodPreset]
                || PERIOD_PRESET_LABELS.custom
            : PERIOD_PRESET_LABELS[periodPreset] ?? 'Período';

    const hasCreditCard = creditCardId !== '' && creditCardId != null;

    const activeFilterCount = useMemo(() => {
        let count = 0;
        if (periodPreset && periodPreset !== PERIOD_PRESET_IDS.current_month) {
            count += 1;
        }
        if (type) {
            count += 1;
        }
        if (categoryId !== '' && categoryId != null) {
            count += 1;
        }
        if (showCreditCardFilter && creditCardId !== '' && creditCardId != null) {
            count += 1;
        }
        if (showDebtorFilter && debtorId !== '' && debtorId != null) {
            count += 1;
        }
        return count;
    }, [
        periodPreset,
        type,
        categoryId,
        showCreditCardFilter,
        creditCardId,
        showDebtorFilter,
        debtorId,
    ]);

    const summaryParts = [
        periodLabel,
        TYPE_LABELS[type] ?? 'Todos',
        selectedCategory?.name ?? 'Todas as categorias',
    ];
    if (showCreditCardFilter) {
        summaryParts.push(selectedCard?.name ?? 'Todos os cartões');
    }
    if (showDebtorFilter) {
        summaryParts.push(selectedDebtor?.name ?? 'Todas as pessoas');
    }

    const creditCardSelect = showCreditCardFilter ? (
        <FilterSelect
            items={creditCards}
            value={creditCardId}
            loading={creditCardsLoading}
            loadingLabel="Cartões…"
            allLabel="Todos os cartões"
            ariaLabel="Cartão"
            onChange={onCreditCardChange}
        />
    ) : null;

    const debtorSelect = showDebtorFilter ? (
        <FilterSelect
            items={debtors}
            value={debtorId}
            loading={debtorsLoading}
            loadingLabel="Pessoas…"
            allLabel="Todas as pessoas"
            ariaLabel="Pessoa"
            onChange={onDebtorChange}
        />
    ) : null;

    return (
        <section
            className={cx('flex flex-col gap-2', className)}
            aria-label="Filtros do dashboard"
        >
            {/* Mobile: search + filter icon */}
            <div className="flex items-center gap-2 sm:hidden">
                <SearchField
                    value={q}
                    onChange={onSearchChange}
                    className="min-w-0 flex-1"
                />
                <button
                    type="button"
                    className={cx(
                        'relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border transition',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
                        activeFilterCount > 0
                            ? 'border-brand bg-brand/15 text-ink'
                            : 'border-border bg-transparent text-ink hover:bg-surface-raised',
                    )}
                    aria-label="Abrir filtros"
                    aria-haspopup="dialog"
                    aria-expanded={filtersOpen}
                    onClick={() => setFiltersOpen(true)}
                >
                    <ListFilter size={18} strokeWidth={1.75} aria-hidden />
                    {activeFilterCount > 0 ? (
                        <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-ink-on-brand">
                            {activeFilterCount}
                        </span>
                    ) : null}
                </button>
            </div>

            {refreshing ? (
                <span className="text-small text-ink-muted sm:hidden" aria-live="polite">
                    Atualizando…
                </span>
            ) : null}

            <p className="truncate text-caption text-ink-muted sm:hidden" title={summaryParts.join(' · ')}>
                {summaryParts.join(' · ')}
            </p>

            {/* Desktop: inline bar */}
            <div className="aura-scroll-x hidden flex-nowrap items-center gap-x-2 gap-y-2 pb-1 sm:flex">
                <DateRangePicker
                    preset={periodPreset}
                    from={from}
                    to={to}
                    cycleOffset={cycleOffset}
                    hasCreditCard={hasCreditCard}
                    maxDays={maxDateRangeDays}
                    onPresetChange={onPeriodChange}
                    onCycleOffsetChange={onCycleOffsetChange}
                    onCustomRange={onCustomRange}
                    className="shrink-0 flex-nowrap"
                />
                <TypePills
                    value={type}
                    onChange={onTypeChange}
                    className="shrink-0 flex-nowrap"
                />
                <CategorySelect
                    categories={categories}
                    value={categoryId}
                    loading={categoriesLoading}
                    onChange={onCategoryChange}
                />
                {creditCardSelect}
                {debtorSelect}
                <SearchField
                    value={q}
                    onChange={onSearchChange}
                    className="min-w-[11rem] max-w-[16rem] flex-1 sm:min-w-[14rem]"
                />
                {refreshing ? (
                    <span className="shrink-0 text-small text-ink-muted" aria-live="polite">
                        Atualizando…
                    </span>
                ) : null}
            </div>

            <Modal
                open={filtersOpen}
                title="Filtros"
                description="Período, tipo, categoria e vínculos das movimentações."
                onClose={() => setFiltersOpen(false)}
                size="sm"
                footer={(
                    <Button
                        type="button"
                        variant="primary"
                        size="sm"
                        className="w-full sm:w-auto"
                        onClick={() => setFiltersOpen(false)}
                    >
                        Aplicar
                    </Button>
                )}
            >
                <div className="flex flex-col gap-5">
                    <div className="flex flex-col gap-2">
                        <p className="text-caption font-medium text-ink-secondary">Período</p>
                        <DateRangePicker
                            preset={periodPreset}
                            from={from}
                            to={to}
                            cycleOffset={cycleOffset}
                            hasCreditCard={hasCreditCard}
                            maxDays={maxDateRangeDays}
                            onPresetChange={onPeriodChange}
                            onCycleOffsetChange={onCycleOffsetChange}
                            onCustomRange={onCustomRange}
                            className="flex-wrap"
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <p className="text-caption font-medium text-ink-secondary">Tipo</p>
                        <TypePills
                            value={type}
                            onChange={onTypeChange}
                            className="flex-wrap"
                        />
                    </div>
                    <div className="flex flex-col gap-2">
                        <p className="text-caption font-medium text-ink-secondary">Categoria</p>
                        <CategorySelect
                            categories={categories}
                            value={categoryId}
                            loading={categoriesLoading}
                            onChange={onCategoryChange}
                        />
                    </div>
                    {showCreditCardFilter ? (
                        <div className="flex flex-col gap-2">
                            <p className="text-caption font-medium text-ink-secondary">Cartão</p>
                            {creditCardSelect}
                        </div>
                    ) : null}
                    {showDebtorFilter ? (
                        <div className="flex flex-col gap-2">
                            <p className="text-caption font-medium text-ink-secondary">Pessoa</p>
                            {debtorSelect}
                        </div>
                    ) : null}
                </div>
            </Modal>
        </section>
    );
}
