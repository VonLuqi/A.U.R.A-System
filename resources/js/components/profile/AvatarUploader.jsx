import { useEffect, useId, useRef, useState } from 'react';
import { Camera, Trash2 } from 'lucide-react';
import { userInitials } from '../../lib/auth';
import { cx } from '../../lib/cx';
import Button from '../ui/Button';

const ACCEPT = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp';
const MAX_BYTES = 2 * 1024 * 1024;

/**
 * AvatarUploader — Etapa I §3.4.
 *
 * @param {{
 *   user: import('../../lib/auth').AuthUser|{ name?: string, email?: string, avatar_url?: string|null },
 *   loading?: boolean,
 *   error?: string,
 *   onUpload: (file: File) => Promise<void>|void,
 *   onRemove: () => Promise<void>|void,
 * }} props
 */
export default function AvatarUploader({
    user,
    loading = false,
    error = '',
    onUpload,
    onRemove,
}) {
    const inputId = useId();
    const inputRef = useRef(null);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [localError, setLocalError] = useState('');

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    useEffect(() => {
        if (!user?.avatar_url && previewUrl) {
            URL.revokeObjectURL(previewUrl);
            setPreviewUrl(null);
        }
    }, [user?.avatar_url, previewUrl]);

    const displayUrl = previewUrl || user?.avatar_url || null;
    const initials = userInitials(user);
    const busy = loading;
    const shownError = localError || error;

    async function handleFileChange(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        setLocalError('');

        if (!file) {
            return;
        }

        if (!ACCEPT.split(',').some((token) => {
            if (token.startsWith('.')) {
                return file.name.toLowerCase().endsWith(token);
            }
            return file.type === token || file.type === 'image/jpg';
        }) && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setLocalError('Use JPEG, PNG ou WebP (máx. 2 MB).');
            return;
        }

        if (file.size > MAX_BYTES) {
            setLocalError('O avatar deve ter no máximo 2 MB.');
            return;
        }

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        setPreviewUrl(URL.createObjectURL(file));

        try {
            await onUpload(file);
        } catch {
            // Erros HTTP tratados no caller / mutation hook.
        }
    }

    async function handleRemove() {
        setLocalError('');

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            setPreviewUrl(null);
        }

        try {
            await onRemove();
        } catch {
            // Erros HTTP tratados no caller / mutation hook.
        }
    }

    return (
        <div className="flex flex-col gap-3" aria-busy={busy || undefined}>
            <div className="flex items-center gap-4">
                <div
                    className={cx(
                        'relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full',
                        'border border-border-subtle bg-surface-raised text-body-lg font-semibold text-ink',
                    )}
                    aria-hidden={Boolean(displayUrl)}
                >
                    {displayUrl ? (
                        <img
                            src={displayUrl}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <span aria-hidden>{initials}</span>
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <p className="text-caption text-ink-secondary">
                        JPEG, PNG ou WebP · até 2 MB
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <input
                            ref={inputRef}
                            id={inputId}
                            type="file"
                            accept={ACCEPT}
                            className="sr-only"
                            disabled={busy}
                            onChange={handleFileChange}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            loading={busy}
                            disabled={busy}
                            aria-controls={inputId}
                            onClick={() => inputRef.current?.click()}
                        >
                            <Camera size={16} strokeWidth={1.75} aria-hidden />
                            {displayUrl ? 'Trocar' : 'Enviar foto'}
                        </Button>
                        {user?.avatar_url || previewUrl ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={busy}
                                onClick={handleRemove}
                            >
                                <Trash2 size={16} strokeWidth={1.75} aria-hidden />
                                Remover
                            </Button>
                        ) : null}
                    </div>
                </div>
            </div>

            {shownError ? (
                <p className="text-caption text-feedback-danger" role="alert">
                    {shownError}
                </p>
            ) : null}
        </div>
    );
}
