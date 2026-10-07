import Button from '../ui/Button';
import Modal from '../ui/Modal';

/**
 * DeleteCategoryDialog — confirmação com aviso de vínculos.
 *
 * @param {{
 *   open: boolean,
 *   category?: {
 *     name?: string,
 *     usage_count?: number,
 *     transactions_count?: number,
 *     goals_count?: number,
 *     aliases_count?: number,
 *   }|null,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function DeleteCategoryDialog({
    open,
    category = null,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const label = category?.name?.trim()
        ? `“${category.name.trim()}”`
        : 'esta categoria';

    const usage = Number(category?.usage_count ?? 0);
    const tx = Number(category?.transactions_count ?? 0);
    const goals = Number(category?.goals_count ?? 0);
    const aliases = Number(category?.aliases_count ?? 0);

    return (
        <Modal
            open={open}
            title="Excluir categoria"
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
                {usage > 0
                    ? `Há ${usage} vínculo${usage === 1 ? '' : 's'} (lançamentos: ${tx}, metas: ${goals}, apelidos: ${aliases}). Eles ficarão sem categoria.`
                    : 'Nenhum lançamento, meta ou apelido está usando esta categoria.'}
            </p>
        </Modal>
    );
}
