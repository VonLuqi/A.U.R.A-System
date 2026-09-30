import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * DeleteLoanDialog — confirmação (PLAN_CARTOES_EMPRESTIMOS §6.4).
 *
 * @param {{
 *   open: boolean,
 *   loan?: { debtor_name?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteLoanDialog({
    open,
    loan = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = loan?.debtor_name?.trim()
        ? `“${loan.debtor_name.trim()}”`
        : 'esta cobrança';

    return (
        <Modal
            open={open}
            title="Excluir cobrança"
            description={`Remover ${label}? Esta ação não pode ser desfeita.`}
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
                O registro da cobrança será removido. Lançamentos já vinculados permanecem no histórico.
            </p>
        </Modal>
    );
}
