import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * @param {{
 *   open: boolean,
 *   debtor?: { name?: string, open_loans_count?: number }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteDebtorDialog({
    open,
    debtor = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = debtor?.name?.trim()
        ? `“${debtor.name.trim()}”`
        : 'esta pessoa';
    const openCount = Number(debtor?.open_loans_count ?? 0);
    const blocked = openCount > 0;

    return (
        <Modal
            open={open}
            title="Excluir pessoa"
            description={
                blocked
                    ? `${label} tem empréstimo(s) em aberto e não pode ser excluída agora.`
                    : `Remover ${label}? Esta ação não pode ser desfeita.`
            }
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
                        {blocked ? 'Fechar' : 'Cancelar'}
                    </Button>
                    {!blocked ? (
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
                    ) : null}
                </>
            )}
        >
            <p className="text-body text-ink-secondary">
                {blocked
                    ? 'Quite ou cancele os empréstimos em aberto desta pessoa antes de excluí-la.'
                    : 'Empréstimos já quitados/cancelados permanecem no histórico; o vínculo com a pessoa é removido.'}
            </p>
        </Modal>
    );
}
