/**
 * FileConstraintsHint — Etapa D §3.2.
 * Formatos aceitos e limite de tamanho (alinhado ao backend max 10 MB).
 */
export default function FileConstraintsHint({ className = '' }) {
    return (
        <p
            className={[
                'text-caption font-normal text-ink-muted',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            CSV, OFX ou QFX · até 10 MB
        </p>
    );
}
