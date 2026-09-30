import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * CancelLoanDialog — marca status cancelled (PLAN_CARTOES_EMPRESTIMOS §6.4).
 *
 * @param {{
 *   open: boolean,
 *   loan?: { debtor_name?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function CancelLoanDialog({
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
            title="Cancelar cobrança"
            description={`Marcar ${label} como cancelada?`}
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
                        Voltar
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
                        Cancelar cobrança
                    </Button>
                </>
            )}
        >
            <p className="text-body text-ink-secondary">
                A cobrança deixa de aparecer como em aberto. Você ainda poderá excluí-la depois.
            </p>
        </Modal>
    );
}
