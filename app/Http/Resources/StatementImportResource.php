<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Statement import row for GET /api/statements (+ show) — Etapa C §5.7.
 *
 * Omits stored_path (absolute or relative) from the API payload.
 *
 * @mixin \App\Models\StatementImport
 */
class StatementImportResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     original_filename: string,
     *     format: string,
     *     source: string,
     *     status: string,
     *     counters: array{rows_total: int, rows_imported: int, rows_skipped: int},
     *     checksum: string|null,
     *     created_at: string|null,
     *     error_message: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $failed = $this->status === \App\Models\StatementImport::STATUS_FAILED;

        return [
            'id' => (int) $this->id,
            'original_filename' => (string) $this->original_filename,
            'format' => (string) $this->format,
            'source' => (string) $this->source,
            'status' => (string) $this->status,
            'counters' => [
                'rows_total' => (int) $this->rows_total,
                'rows_imported' => (int) $this->rows_imported,
                'rows_skipped' => (int) $this->rows_skipped,
            ],
            'checksum' => $this->checksum !== null ? (string) $this->checksum : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'error_message' => $failed && $this->error_message !== null
                ? (string) $this->error_message
                : null,
        ];
    }
}
