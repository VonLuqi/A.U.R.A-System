import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * DeleteTransactionDialog — confirmação explícita (PLAN_EXPANSAO §8.2).
 *
 * @param {{
 *   open: boolean,
 *   transaction?: { description?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteTransactionDialog({
    open,
    transaction = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = transaction?.description?.trim()
        ? `“${transaction.description.trim()}”`
        : 'este lançamento';

    return (
        <Modal
            open={open}
            title="Excluir lançamento"
            description={`Tem certeza que deseja excluir ${label}? Esta ação não pode ser desfeita.`}
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
                O lançamento será removido permanentemente da sua conta.
            </p>
        </Modal>
    );
}
