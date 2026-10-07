import { useState } from 'react';
import { toast } from 'sonner';
import { createCategory } from '../../api/categories';
import { getErrorMessage, getValidationErrors } from '../../lib/errors';
import { TOAST_DURATION } from '../../lib/toast';
import { cx } from '../../lib/cx';
import Button from '../ui/Button';
import Input from '../ui/Input';

/**
 * CategoryFormSelect — choose category + inline create.
 *
 * @param {{
 *   id?: string,
 *   categories?: Array<{ id: number, name: string }>,
 *   value: string,
 *   onChange: (value: string) => void,
 *   onCreated?: (category: { id: number, name: string }) => void,
 *   type?: 'income'|'expense'|'transfer'|string,
 *   disabled?: boolean,
 *   invalid?: boolean,
 *   emptyLabel?: string,
 * }} props
 */
export default function CategoryFormSelect({
    id,
    categories = [],
    value = '',
    onChange,
    onCreated,
    type = 'expense',
    disabled = false,
    invalid = false,
    emptyLabel = 'Sem categoria',
}) {
    const [creating, setCreating] = useState(false);
    const [newName, setNewName] = useState('');
    const [busy, setBusy] = useState(false);

    async function handleCreate() {
        const name = newName.trim();
        if (!name) {
            toast.error('Informe o nome da categoria.', { duration: TOAST_DURATION });
            return;
        }

        setBusy(true);
        try {
            const category = await createCategory({ name, type: type || 'expense' });
            onChange(String(category.id));
            onCreated?.(category);
            setNewName('');
            setCreating(false);
            toast.success(`Categoria “${category.name}” criada.`);
        } catch (err) {
            const validation = getValidationErrors(err);
            toast.error(validation?.name?.[0] || getErrorMessage(err), {
                duration: TOAST_DURATION,
            });
        } finally {
            setBusy(false);
        }
    }

    const selectClass = cx(
        'h-11 w-full rounded-lg border bg-surface-sunken px-3 font-sans text-body text-ink',
        'outline-none transition-[border-color,box-shadow]',
        'focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand',
        'disabled:cursor-not-allowed disabled:opacity-60',
        invalid ? 'border-feedback-danger' : 'border-border',
    );

    return (
        <div className="flex flex-col gap-2">
            <select
                id={id}
                value={value}
                disabled={disabled || busy}
                aria-invalid={invalid || undefined}
                className={selectClass}
                onChange={(event) => {
                    const next = event.target.value;
                    if (next === '__create__') {
                        setCreating(true);
                        return;
                    }
                    onChange(next);
                }}
            >
                <option value="">{emptyLabel}</option>
                {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
                <option value="__create__">+ Nova categoria…</option>
            </select>

            {creating ? (
                <div className="flex flex-col gap-2 rounded-lg border border-border-subtle bg-surface-sunken/50 p-3 sm:flex-row sm:items-end">
                    <label className="flex min-w-0 flex-1 flex-col gap-1">
                        <span className="text-caption font-medium text-ink-secondary">
                            Nome da categoria
                        </span>
                        <Input
                            value={newName}
                            disabled={busy || disabled}
                            maxLength={80}
                            placeholder="Ex.: Mercado"
                            autoFocus
                            onChange={(event) => setNewName(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    handleCreate();
                                }
                            }}
                        />
                    </label>
                    <div className="flex shrink-0 gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={busy}
                            onClick={() => {
                                setCreating(false);
                                setNewName('');
                            }}
                        >
                            Cancelar
                        </Button>
                        <Button type="button" size="sm" disabled={busy} onClick={handleCreate}>
                            Criar
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
