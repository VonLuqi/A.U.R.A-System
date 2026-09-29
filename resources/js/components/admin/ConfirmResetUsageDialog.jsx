import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * ConfirmResetUsageDialog — reset cotas (PLAN_EXPANSAO §8.6).
 *
 * @param {{
 *   open: boolean,
 *   user?: { name?: string, email?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function ConfirmResetUsageDialog({
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
            title="Reiniciar cotas"
            description={`Zerar uploads e lançamentos manuais usados de ${label}?`}
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
                        size="sm"
                        loading={submitting}
                        disabled={submitting}
                        onClick={() => {
                            void onConfirm();
                        }}
                    >
                        Reiniciar
                    </Button>
                </>
            )}
        >
            <p className="text-body text-ink-secondary">
                O período de cota volta ao início do mês corrente. Limites do papel não mudam.
            </p>
        </Modal>
    );
}
