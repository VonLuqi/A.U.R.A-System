import { useEffect, useId, useState } from 'react';
import Button from '../ui/Button';
import Input from '../ui/Input';
import Label from '../ui/Label';
import Modal from '../ui/Modal';

export const WIPE_CONFIRMATION_PHRASE = 'APAGAR TUDO';

/**
 * ConfirmWipeTransactionsDialog — tripla confirmação + frase maiúscula.
 *
 * @param {{
 *   open: boolean,
 *   transactionCount?: number|null,
 *   countLoading?: boolean,
 *   submitting?: boolean,
 *   onClose: () => void,
 *   onConfirm: () => void | Promise<void>,
 * }} props
 */
export default function ConfirmWipeTransactionsDialog({
    open,
    transactionCount = null,
    countLoading = false,
    submitting = false,
    onClose,
    onConfirm,
}) {
    const inputId = useId();
    const [step, setStep] = useState(1);
    const [confirmation, setConfirmation] = useState('');

    useEffect(() => {
        if (!open) {
            setStep(1);
            setConfirmation('');
        }
    }, [open]);

    const phraseMatches = confirmation === WIPE_CONFIRMATION_PHRASE;
    const countLabel =
        countLoading || transactionCount == null
            ? '…'
            : String(transactionCount);

    const titles = {
        1: 'Excluir todo o histórico?',
        2: 'Confirma novamente',
        3: 'Digite a confirmação',
    };

    const descriptions = {
        1: 'Esta ação apaga todas as suas movimentações de forma permanente.',
        2: `Você está prestes a excluir ${countLabel} lançamento${transactionCount === 1 ? '' : 's'}. Não há como desfazer.`,
        3: `Para continuar, digite exatamente ${WIPE_CONFIRMATION_PHRASE} (maiúsculas).`,
    };

    function handleClose() {
        if (submitting) {
            return;
        }

        onClose();
    }

    function footer() {
        if (step === 1) {
            return (
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={submitting}
                        onClick={handleClose}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        disabled={submitting}
                        onClick={() => setStep(2)}
                    >
                        Continuar
                    </Button>
                </>
            );
        }

        if (step === 2) {
            return (
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={submitting}
                        onClick={handleClose}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        variant="danger"
                        size="sm"
                        disabled={submitting || countLoading}
                        onClick={() => setStep(3)}
                    >
                        Continuar
                    </Button>
                </>
            );
        }

        return (
            <>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    disabled={submitting}
                    onClick={handleClose}
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    variant="danger"
                    size="sm"
                    loading={submitting}
                    disabled={submitting || !phraseMatches}
                    onClick={() => {
                        void onConfirm();
                    }}
                >
                    Excluir tudo
                </Button>
            </>
        );
    }

    return (
        <Modal
            open={open}
            title={titles[step]}
            description={descriptions[step]}
            onClose={handleClose}
            closeOnScrim={!submitting}
            size="sm"
            footer={footer()}
        >
            {step === 1 ? (
                <p className="text-body text-ink-secondary">
                    Metas, apelidos, cartões e cobranças permanecem. Apenas o
                    histórico de transações será removido.
                </p>
            ) : null}

            {step === 2 ? (
                <p className="text-body text-ink-secondary">
                    Etapa 2 de 3 — confirme que entendeu o risco antes de digitar
                    a frase de exclusão.
                </p>
            ) : null}

            {step === 3 ? (
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={inputId}>
                        Frase de confirmação
                    </Label>
                    <Input
                        id={inputId}
                        value={confirmation}
                        disabled={submitting}
                        autoComplete="off"
                        spellCheck={false}
                        placeholder={WIPE_CONFIRMATION_PHRASE}
                        onChange={(event) => setConfirmation(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && phraseMatches && !submitting) {
                                event.preventDefault();
                                void onConfirm();
                            }
                        }}
                    />
                    <p className="text-caption text-ink-muted">
                        A frase precisa coincidir exatamente, incluindo espaços
                        e maiúsculas.
                    </p>
                </div>
            ) : null}
        </Modal>
    );
}
