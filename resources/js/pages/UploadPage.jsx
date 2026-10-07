import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { FileText } from 'lucide-react';
import { toast } from 'sonner';
import { uploadStatement } from '../api/statements';
import { TOAST_DURATION_UPLOAD } from '../lib/toast';
import {
    formatMismatchAction,
    statementKindOption,
} from '../lib/statementKinds';
import { featureEnabled } from '../lib/auth';
import PageHeader from '../components/layout/PageHeader';
import Button from '../components/ui/Button';
import ErrorState from '../components/ui/ErrorState';
import Label from '../components/ui/Label';
import Dropzone from '../components/upload/Dropzone';
import FileConstraintsHint from '../components/upload/FileConstraintsHint';
import RowErrorsList from '../components/upload/RowErrorsList';
import StatementKindPills from '../components/upload/StatementKindPills';
import UploadSummaryCard from '../components/upload/UploadSummaryCard';
import { useAuth } from '../hooks/useAuth';
import { useCreditCards } from '../hooks/useCreditCards';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

/**
 * UploadPage — Etapa D §3.2–§3.6.3 / §5.5 / PLAN_EXPANSAO §8.4 / §9.3.
 * Arquivo fica pendente até o usuário confirmar (tipo/cartão editáveis).
 */
export default function UploadPage() {
    useDocumentTitle('Upload · Aura');
    const { user } = useAuth();
    const allowCreditCard = featureEnabled(user, 'credit_card_upload');
    const allowCardsFeature = featureEnabled(user, 'credit_cards');

    const [kind, setKind] = useState(/** @type {'checking'|'credit_card'} */ ('checking'));
    const [creditCardId, setCreditCardId] = useState('');
    const [pendingFile, setPendingFile] = useState(/** @type {File|null} */ (null));
    const [uploading, setUploading] = useState(false);
    const [summary, setSummary] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const [errorReference, setErrorReference] = useState(null);
    const [errorCode, setErrorCode] = useState(/** @type {string|null} */ (null));

    const cards = useCreditCards({
        is_active: 1,
        per_page: 50,
        enabled: allowCardsFeature,
    });

    useEffect(() => {
        if (!allowCreditCard && kind === 'credit_card') {
            setKind('checking');
        }
    }, [allowCreditCard, kind]);

    useEffect(() => {
        if (!allowCardsFeature || kind !== 'credit_card' || cards.status !== 'success') {
            return;
        }

        const defaultCard = cards.data.find((card) => card.is_default);
        const sole = cards.data.length === 1 ? cards.data[0] : null;
        const preferred = defaultCard ?? sole;

        if (preferred && !creditCardId) {
            setCreditCardId(String(preferred.id));
        }
    }, [allowCardsFeature, kind, cards.status, cards.data, creditCardId]);

    const kindOption = statementKindOption(kind);

    const mismatchAction = useMemo(
        () => formatMismatchAction({ errorCode }, kind),
        [errorCode, kind],
    );

    const resetUpload = useCallback(() => {
        setPendingFile(null);
        setSummary(null);
        setErrorMessage('');
        setErrorReference(null);
        setErrorCode(null);
        setUploading(false);
    }, []);

    const handleKindChange = useCallback((nextKind) => {
        setKind(nextKind);
        setSummary(null);
        setErrorMessage('');
        setErrorReference(null);
        setErrorCode(null);
        if (nextKind !== 'credit_card') {
            setCreditCardId('');
        }
    }, []);

    const handleFileAccepted = useCallback((file) => {
        setPendingFile(file);
        setSummary(null);
        setErrorMessage('');
        setErrorReference(null);
        setErrorCode(null);
    }, []);

    const handleConfirmUpload = useCallback(async () => {
        if (!pendingFile || uploading) {
            return;
        }

        setUploading(true);
        setSummary(null);
        setErrorMessage('');
        setErrorReference(null);
        setErrorCode(null);

        const option = statementKindOption(kind);

        try {
            const data = await uploadStatement(pendingFile, {
                source: option.source,
                statement_kind: option.statement_kind,
                credit_card_id: creditCardId ? Number(creditCardId) : null,
            });
            setSummary(data);
            setPendingFile(null);
        } catch (error) {
            const message = error?.message || 'Não foi possível importar o extrato.';
            const importId = error?.importId ?? null;
            const code = typeof error?.errorCode === 'string' ? error.errorCode : null;

            setErrorMessage(message);
            setErrorReference(importId);
            setErrorCode(code);
            toast.error(message, { duration: TOAST_DURATION_UPLOAD });
        } finally {
            setUploading(false);
        }
    }, [pendingFile, uploading, kind, creditCardId]);

    const rowErrors = summary?.row_errors ?? [];
    const selectorsDisabled = uploading;

    return (
        <div className="flex flex-col gap-10">
            <PageHeader
                title="Importar extrato"
                description="Envie o extrato da conta ou a fatura do cartão Nubank. A Aura organiza as movimentações por você."
            />

            <section
                className="mx-auto flex w-full max-w-2xl flex-col gap-4"
                aria-label="Importação de extrato"
            >
                {!summary ? (
                    <div className="flex flex-col gap-3">
                        <p className="text-caption font-medium text-ink-secondary">
                            Tipo de arquivo
                        </p>
                        <StatementKindPills
                            value={kind}
                            onChange={handleKindChange}
                            disabled={selectorsDisabled}
                            allowCreditCard={allowCreditCard}
                        />
                        {allowCardsFeature ? (
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="upload-credit-card">
                                    Cartão destino (opcional)
                                </Label>
                                <select
                                    id="upload-credit-card"
                                    className="w-full rounded-lg border border-border bg-surface-sunken px-3 py-2.5 font-sans text-body text-ink outline-none transition-[border-color,box-shadow] focus-visible:border-brand focus-visible:ring-1 focus-visible:ring-brand disabled:cursor-not-allowed disabled:opacity-60"
                                    value={creditCardId}
                                    disabled={selectorsDisabled || cards.status === 'loading'}
                                    onChange={(event) => setCreditCardId(event.target.value)}
                                >
                                    <option value="">
                                        {kind === 'credit_card'
                                            ? 'Automático (padrão / único ativo)'
                                            : 'Sem vínculo com cartão'}
                                    </option>
                                    {cards.data.map((card) => (
                                        <option key={card.id} value={card.id}>
                                            {card.name}
                                            {card.is_default ? ' · padrão' : ''}
                                            {card.last_four ? ` ···· ${card.last_four}` : ''}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-caption text-ink-muted">
                                    {kind === 'credit_card'
                                        ? 'Lançamentos da fatura serão vinculados a este cartão. Reimportar atualiza o vínculo sem duplicar.'
                                        : 'Opcional: vincula saídas deste extrato ao cartão. Reimportar atualiza o vínculo sem duplicar.'}
                                </p>
                            </div>
                        ) : null}
                        <FileConstraintsHint kind={kind} />
                    </div>
                ) : null}

                {!summary && !pendingFile ? (
                    <Dropzone uploading={uploading} onFileAccepted={handleFileAccepted} />
                ) : null}

                {!summary && pendingFile ? (
                    <div
                        className="flex flex-col gap-4 rounded-xl border border-border bg-surface-sunken px-5 py-5"
                        aria-label="Arquivo selecionado"
                    >
                        <div className="flex items-start gap-3">
                            <FileText
                                size={22}
                                strokeWidth={1.5}
                                className="mt-0.5 shrink-0 text-brand"
                                aria-hidden
                            />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-body font-medium text-ink">
                                    {pendingFile.name}
                                </p>
                                <p className="mt-1 text-caption text-ink-muted">
                                    Confira o tipo e o cartão destino antes de importar.
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                loading={uploading}
                                disabled={uploading}
                                onClick={() => {
                                    void handleConfirmUpload();
                                }}
                            >
                                Confirmar importação
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                disabled={uploading}
                                onClick={() => {
                                    setPendingFile(null);
                                    setErrorMessage('');
                                    setErrorReference(null);
                                    setErrorCode(null);
                                }}
                            >
                                Trocar arquivo
                            </Button>
                        </div>
                    </div>
                ) : null}

                {errorMessage ? (
                    <ErrorState
                        title="Não foi possível importar"
                        message={errorMessage}
                        hint={mismatchAction?.hint}
                        reference={errorReference}
                        onRetry={resetUpload}
                        secondaryAction={
                            mismatchAction
                                ? {
                                      label: mismatchAction.label,
                                      onClick: () => {
                                          handleKindChange(mismatchAction.nextKind);
                                      },
                                  }
                                : null
                        }
                    />
                ) : null}

                {summary ? (
                    <UploadSummaryCard summary={summary} onReset={resetUpload} />
                ) : null}

                {rowErrors.length > 0 ? (
                    <RowErrorsList
                        errors={rowErrors}
                        totalCount={summary?.row_errors_count ?? rowErrors.length}
                    />
                ) : null}

                {!summary ? (
                    <div className="flex justify-center pt-2">
                        <Link
                            to="/dashboard"
                            className="inline-flex h-9 items-center justify-center rounded-full border border-border px-4 text-caption font-medium text-ink no-underline transition hover:bg-surface-raised"
                        >
                            Ver dashboard
                        </Link>
                    </div>
                ) : null}

                {summary ? (
                    <p className="text-center text-caption text-ink-muted">
                        Importado como {kindOption.label.toLowerCase()}.
                    </p>
                ) : null}
            </section>
        </div>
    );
}
