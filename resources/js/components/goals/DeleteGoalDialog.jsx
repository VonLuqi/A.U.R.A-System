import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * DeleteGoalDialog — confirmação (PLAN_EXPANSAO §8.5).
 *
 * @param {{
 *   open: boolean,
 *   goal?: { name?: string }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteGoalDialog({
    open,
    goal = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = goal?.name?.trim() ? `“${goal.name.trim()}”` : 'esta meta';

    return (
        <Modal
            open={open}
            title="Excluir meta"
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
                O progresso e o vínculo com lançamentos desta meta serão removidos.
            </p>
        </Modal>
    );
}
