<?php

namespace App\DTOs;

use App\Models\StatementImport;
use InvalidArgumentException;

/**
 * API summary returned after statement upload (Etapa C §3.2.3 / §5.3).
 *
 * @phpstan-type RowError array{line: int, message: string}
 */
final readonly class UploadSummary
{
    public const MAX_ROW_ERRORS = 20;

    /**
     * @param  list<RowError>  $rowErrors
     */
    public function __construct(
        public int $importId,
        public string $status,
        public string $format,
        public string $source,
        public string $originalFilename,
        public int $rowsTotal,
        public int $rowsImported,
        public int $rowsSkipped,
        public array $rowErrors,
        public string $checksum,
    ) {
        if ($this->importId < 1) {
            throw new InvalidArgumentException('importId must be >= 1.');
        }

        if (! in_array($this->format, ['csv', 'ofx'], true)) {
            throw new InvalidArgumentException("format must be csv|ofx, got [{$this->format}].");
        }

        foreach (['rowsTotal', 'rowsImported', 'rowsSkipped'] as $counter) {
            if ($this->{$counter} < 0) {
                throw new InvalidArgumentException("{$counter} must be >= 0.");
            }
        }

        foreach ($this->rowErrors as $index => $error) {
            if (! is_array($error) || ! isset($error['line'], $error['message'])) {
                throw new InvalidArgumentException("rowErrors[{$index}] must have line and message keys.");
            }
        }
    }

    /**
     * @param  list<RowError>  $rowErrors
     */
    public static function fromImport(StatementImport $import, array $rowErrors = []): self
    {
        return new self(
            importId: (int) $import->id,
            status: (string) $import->status,
            format: (string) $import->format,
            source: (string) $import->source,
            originalFilename: (string) $import->original_filename,
            rowsTotal: (int) $import->rows_total,
            rowsImported: (int) $import->rows_imported,
            rowsSkipped: (int) $import->rows_skipped,
            rowErrors: $rowErrors,
            checksum: (string) ($import->checksum ?? ''),
        );
    }

    /**
     * JSON payload under `data` for POST /api/statements/upload.
     *
     * @return array{
     *     import_id: int,
     *     status: string,
     *     format: string,
     *     source: string,
     *     original_filename: string,
     *     checksum: string,
     *     rows_total: int,
     *     rows_imported: int,
     *     rows_skipped: int,
     *     row_errors_count: int,
     *     row_errors: list<RowError>
     * }
     */
    public function toArray(): array
    {
        $truncated = array_slice($this->rowErrors, 0, self::MAX_ROW_ERRORS);

        return [
            'import_id' => $this->importId,
            'status' => $this->status,
            'format' => $this->format,
            'source' => $this->source,
            'original_filename' => $this->originalFilename,
            'checksum' => $this->checksum,
            'rows_total' => $this->rowsTotal,
            'rows_imported' => $this->rowsImported,
            'rows_skipped' => $this->rowsSkipped,
            'row_errors_count' => count($this->rowErrors),
            'row_errors' => array_values($truncated),
        ];
    }
}
