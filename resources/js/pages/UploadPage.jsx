import { useCallback, useState } from 'react';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';
import { uploadStatement } from '../api/statements';
import { TOAST_DURATION_UPLOAD } from '../lib/toast';
import PageHeader from '../components/layout/PageHeader';
import ErrorState from '../components/ui/ErrorState';
import Dropzone from '../components/upload/Dropzone';
import FileConstraintsHint from '../components/upload/FileConstraintsHint';
import RowErrorsList from '../components/upload/RowErrorsList';
import UploadSummaryCard from '../components/upload/UploadSummaryCard';
import { useDocumentTitle } from '../hooks/useDocumentTitle';

/**
 * UploadPage — Etapa D §3.2–§3.6.3 / §5.5.
 */
export default function UploadPage() {
    useDocumentTitle('Upload · Aura');

    const [uploading, setUploading] = useState(false);
    const [summary, setSummary] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const [errorReference, setErrorReference] = useState(null);

    const resetUpload = useCallback(() => {
        setSummary(null);
        setErrorMessage('');
        setErrorReference(null);
        setUploading(false);
    }, []);

    const handleFileAccepted = useCallback(async (file) => {
        setUploading(true);
        setSummary(null);
        setErrorMessage('');
        setErrorReference(null);

        try {
            const data = await uploadStatement(file);
            setSummary(data);
        } catch (error) {
            const message = error?.message || 'Não foi possível importar o extrato.';
            const importId = error?.importId ?? null;

            setErrorMessage(message);
            setErrorReference(importId);
            toast.error(message, { duration: TOAST_DURATION_UPLOAD });
        } finally {
            setUploading(false);
        }
    }, []);

    const rowErrors = summary?.row_errors ?? [];

    return (
        <div className="flex flex-col gap-10">
            <PageHeader
                title="Importar extrato"
                description="Envie o extrato do Nubank. A Aura organiza as movimentações por você."
            />

            <section
                className="mx-auto flex w-full max-w-2xl flex-col gap-4"
                aria-label="Importação de extrato"
            >
                <FileConstraintsHint />

                {!summary ? (
                    <Dropzone uploading={uploading} onFileAccepted={handleFileAccepted} />
                ) : null}

                {errorMessage ? (
                    <ErrorState
                        title="Não foi possível importar"
                        message={errorMessage}
                        reference={errorReference}
                        onRetry={resetUpload}
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
            </section>
        </div>
    );
}
