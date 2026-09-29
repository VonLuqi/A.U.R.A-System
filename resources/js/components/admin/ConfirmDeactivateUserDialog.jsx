import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * ConfirmDeactivateUserDialog — soft-block (PLAN_EXPANSAO §8.6).
 *
 * @param {{
 *   open: boolean,
 *   user?: { name?: string, email?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function ConfirmDeactivateUserDialog({
    open,
    user = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = user?.email || user?.name || 'este usuário';

    return (
        <Modal
            open={open}
            title="Desativar usuário"
            description={`Desativar ${label}? A conta deixa de acessar o Aura (soft-block).`}
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
                        Desativar
                    </Button>
                </>
            )}
        >
            <p className="text-body text-ink-secondary">
                Os dados financeiros permanecem; a conta pode ser reativada depois pela edição.
            </p>
        </Modal>
    );
}
