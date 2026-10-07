import { useCallback, useId, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { Upload } from 'lucide-react';
import { toast } from 'sonner';
import { TOAST_DURATION_UPLOAD } from '../../lib/toast';
import {
    ALLOWED_EXTENSIONS,
    MAX_STATEMENT_BYTES,
    getExtension,
    validateStatementFile,
} from '../../lib/validators';
import Spinner from '../ui/Spinner';

const ACCEPT = {
    'text/csv': ['.csv'],
    'application/x-ofx': ['.ofx', '.qfx'],
    'application/vnd.intu.qfx': ['.qfx'],
    'text/plain': ['.csv', '.ofx', '.qfx'],
};

/**
 * Extensão obrigatória — MIME no Windows é inconsistente.
 * @param {File} file
 * @returns {import('react-dropzone').FileError|null}
 */
function extensionValidator(file) {
    const ext = getExtension(file.name);

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        return {
            code: 'file-invalid-type',
            message: 'Formato não suportado. Use CSV ou OFX (também .qfx).',
        };
    }

    return null;
}

/**
 * @param {import('react-dropzone').FileRejection[]} rejections
 * @returns {string}
 */
function rejectionMessage(rejections) {
    const error = rejections[0]?.errors?.[0];

    if (!error) {
        return 'Não foi possível aceitar o arquivo.';
    }

    if (error.code === 'file-too-large') {
        return 'O arquivo deve ter no máximo 10 MB.';
    }

    if (error.code === 'file-invalid-type') {
        return error.message || 'Formato não suportado. Use CSV ou OFX (também .qfx).';
    }

    return error.message || 'Não foi possível aceitar o arquivo.';
}

/**
 * Dropzone — Etapa D §3.3 (react-dropzone).
 * Seleciona o arquivo; o parent confirma antes de enviar.
 *
 * @param {{
 *   uploading?: boolean,
 *   disabled?: boolean,
 *   onFileAccepted?: (file: File) => void,
 * }} props
 */
export default function Dropzone({ uploading = false, disabled = false, onFileAccepted }) {
    const instructionsId = useId();
    const [rejectMessage, setRejectMessage] = useState('');
    const isDisabled = disabled || uploading;

    const onDrop = useCallback(
        (acceptedFiles, fileRejections) => {
            setRejectMessage('');

            if (fileRejections.length > 0) {
                const message = rejectionMessage(fileRejections);
                setRejectMessage(message);
                toast.error(message, { duration: TOAST_DURATION_UPLOAD });
                return;
            }

            const file = acceptedFiles[0];

            if (!file) {
                return;
            }

            const validation = validateStatementFile(file);

            if (!validation.ok) {
                setRejectMessage(validation.message);
                toast.error(validation.message, { duration: TOAST_DURATION_UPLOAD });
                return;
            }

            onFileAccepted?.(file);
        },
        [onFileAccepted],
    );

    const { getRootProps, getInputProps, isDragActive, open } = useDropzone({
        onDrop,
        accept: ACCEPT,
        maxSize: MAX_STATEMENT_BYTES,
        multiple: false,
        disabled: isDisabled,
        noClick: false,
        noKeyboard: false,
        validator: extensionValidator,
    });

    const rootProps = getRootProps({
        role: 'button',
        tabIndex: isDisabled ? -1 : 0,
        'aria-disabled': isDisabled || undefined,
        'aria-describedby': instructionsId,
        'aria-busy': uploading || undefined,
        onKeyDown: (event) => {
            if (isDisabled) {
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        },
    });

    let title = 'Arraste o arquivo aqui ou clique para selecionar';

    if (isDragActive && !uploading) {
        title = 'Solte para selecionar';
    }

    return (
        <div className="flex flex-col gap-2">
            <div
                {...rootProps}
                className={[
                    'relative flex min-h-[180px] flex-col items-center justify-center gap-3 overflow-hidden rounded-xl border border-dashed px-6 py-10 text-center transition-colors',
                    'outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-canvas',
                    isDisabled && !uploading ? 'cursor-not-allowed opacity-70' : '',
                    uploading ? 'cursor-wait' : isDisabled ? '' : 'cursor-pointer',
                    isDragActive && !isDisabled
                        ? 'border-brand bg-surface'
                        : 'border-border bg-surface-sunken',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                <input {...getInputProps()} />

                <Upload
                    size={28}
                    strokeWidth={1.5}
                    className={[
                        isDragActive ? 'text-brand' : 'text-ink-secondary',
                        uploading ? 'opacity-30' : '',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                    aria-hidden
                />

                <p className={['text-body font-medium text-ink', uploading ? 'opacity-30' : ''].filter(Boolean).join(' ')}>
                    {title}
                </p>
                <p
                    id={instructionsId}
                    className={[
                        'text-caption text-ink-muted',
                        uploading ? 'opacity-30' : '',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                >
                    CSV, OFX ou QFX · até 10 MB. Enter ou Espaço abre o seletor.
                </p>

                {uploading ? (
                    <div
                        className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-canvas/70 backdrop-blur-[1px]"
                        aria-live="polite"
                    >
                        <Spinner size="lg" />
                        <p className="text-body font-medium text-ink">Processando extrato…</p>
                    </div>
                ) : null}
            </div>

            {rejectMessage ? (
                <p className="text-caption text-feedback-danger" role="alert">
                    {rejectMessage}
                </p>
            ) : null}
        </div>
    );
}
