import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * DeleteAliasDialog — confirmação (PLAN_EXPANSAO §8.7).
 *
 * @param {{
 *   open: boolean,
 *   alias?: { display_name?: string, match_pattern?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteAliasDialog({
    open,
    alias = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = alias?.display_name?.trim()
        ? `“${alias.display_name.trim()}”`
        : 'esta regra';

    return (
        <Modal
            open={open}
            title="Excluir apelido"
            description={`Remover ${label}? Lançamentos já renomeados não são revertidos.`}
            onClose={onClose}
            closeOnScrim={!submitting}
            size="sm"
            footer={(
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={submitting}
                        onClick={onClose}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        loading={submitting}
                        disabled={submitting}
                        onClick={() => {
                            void onConfirm();
                        }}
                    >
                        Excluir
                    </Button>
                </>
            )}
        >
            <p className="text-body text-ink-secondary">
                Padrão: {alias?.match_pattern || '—'}. Novos matches deixam de usar esta regra.
            </p>
        </Modal>
    );
}
