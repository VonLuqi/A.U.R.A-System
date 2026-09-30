import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * DeleteCreditCardDialog — confirmação (PLAN_CARTOES_EMPRESTIMOS §6.3).
 *
 * @param {{
 *   open: boolean,
 *   card?: { name?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteCreditCardDialog({
    open,
    card = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = card?.name?.trim() ? `“${card.name.trim()}”` : 'este cartão';

    return (
        <Modal
            open={open}
            title="Excluir cartão"
            description={`Remover ${label}?`}
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
                Se houver cobranças em aberto vinculadas a este cartão, a exclusão será bloqueada.
                Lançamentos já vinculados mantêm o histórico.
            </p>
        </Modal>
    );
}
