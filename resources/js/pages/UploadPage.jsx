import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import { uploadStatement } from '../api/statements';
import { TOAST_DURATION_UPLOAD } from '../lib/toast';
import {
    formatMismatchAction,
    statementKindOption,
} from '../lib/statementKinds';
import { featureEnabled } from '../lib/auth';
import PageHeader from '../components/layout/PageHeader';
import ErrorState from '../components/ui/ErrorState';
import Dropzone from '../components/upload/Dropzone';
import FileConstraintsHint from '../components/upload/FileConstraintsHint';
import RowErrorsList from '../components/upload/RowErrorsList';
import StatementKindPills from '../components/upload/StatementKindPills';
import UploadSummaryCard from '../components/upload/UploadSummaryCard';
import { useAuth } from '../hooks/useAuth';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

/**
 * UploadPage — Etapa D §3.2–§3.6.3 / §5.5 / PLAN_EXPANSAO §8.4 / §9.3.
 */
export default function UploadPage() {
    useDocumentTitle('Upload · Aura');
    const { user } = useAuth();
    const allowCreditCard = featureEnabled(user, 'credit_card_upload');

    const [kind, setKind] = useState(/** @type {'checking'|'credit_card'} */ ('checking'));
    const [uploading, setUploading] = useState(false);
    const [summary, setSummary] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const [errorReference, setErrorReference] = useState(null);
    const [errorCode, setErrorCode] = useState(/** @type {string|null} */ (null));

    useEffect(() => {
        if (!allowCreditCard && kind === 'credit_card') {
            setKind('checking');
        }
    }, [allowCreditCard, kind]);

    const kindOption = statementKindOption(kind);

    const mismatchAction = useMemo(
        () => formatMismatchAction({ errorCode }, kind),
        [errorCode, kind],
    );

    const resetUpload = useCallback(() => {
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
    }, []);

    const handleFileAccepted = useCallback(
        async (file) => {
            setUploading(true);
            setSummary(null);
            setErrorMessage('');
            setErrorReference(null);
            setErrorCode(null);

            const option = statementKindOption(kind);

            try {
                const data = await uploadStatement(file, {
                    source: option.source,
                    statement_kind: option.statement_kind,
                });
                setSummary(data);
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
        },
        [kind],
    );

    const rowErrors = summary?.row_errors ?? [];

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
                            disabled={uploading}
                            allowCreditCard={allowCreditCard}
                        />
                        <FileConstraintsHint kind={kind} />
                    </div>
                ) : null}

                {!summary ? (
                    <Dropzone uploading={uploading} onFileAccepted={handleFileAccepted} />
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
