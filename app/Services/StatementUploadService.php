<?php

namespace App\Services;

use App\DTOs\ParseResult;
use App\DTOs\ParsedTransaction;
use App\DTOs\UploadSummary;
use App\Exceptions\InvalidStatementException;
use App\Exceptions\UnsupportedStatementFormatException;
use App\Enums\TransactionSourceKind;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Parsers\StatementParserResolver;
use App\Support\StatementFormatDetector;
use App\Support\StatementStorage;
use App\Support\TransactionHasher;
use App\Services\UsageLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Single orchestration entry for statement upload (Etapa C §4.1).
 *
 * Flow:
 * 1. Validation already done in FormRequest
 * 2. Persist file on disk `statements`
 * 3. Compute checksum
 * 4. Create StatementImport (status=processing)
 * 5. Resolve parser + parse
 * 6. DB::transaction — insert transactions + update counters
 * 7. Mark completed or failed
 * 8. Return UploadSummary
 */
final class StatementUploadService
{
    private const INSERT_CHUNK = 200;

    private const ERROR_MESSAGE_MAX = 1000;

    public function __construct(
        private readonly StatementParserResolver $parsers,
        private readonly UsageLimitService $usageLimits,
        private readonly AliasResolutionService $aliases,
        private readonly CreditCardService $creditCards,
        private readonly InstallmentPlanService $installmentPlans,
    ) {}

    public function handle(
        User $user,
        UploadedFile $file,
        string $source = 'nubank',
        ?string $statementKind = null,
        ?int $creditCardId = null,
    ): UploadSummary {
        $detected = StatementFormatDetector::detectForImport($file, $source, $statementKind);
        $format = $detected['format'];
        $source = $detected['source'];

        // §4.3.1 — persist on disk `statements` + SHA-256 checksum + sanitized basename.
        // Uses StatementStorage (putFileAs under the hood); no StatementImport if store fails.
        $stored = StatementStorage::storeUploadedFile($file, (int) $user->id);

        // §4.3.2 — create import as processing only after successful store (outside DB::transaction).
        $import = StatementImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => $stored['original_filename'],
            'stored_path' => $stored['stored_path'],
            'format' => $format,
            'source' => $source,
            'status' => StatementImport::STATUS_PROCESSING,
            'rows_total' => 0,
            'rows_imported' => 0,
            'rows_skipped' => 0,
            'checksum' => $stored['checksum'],
            'error_message' => null,
        ]);

        try {
            // §4.3.3 — resolve parser + parse; on InvalidStatementException mark failed and re-throw (HTTP 422).
            $absolutePath = Storage::disk(StatementStorage::DISK)->path($stored['stored_path']);
            $parseResult = $this->parsers->resolve($format, $source)->parse($absolutePath);

            // Step 6–7: persist atomically + mark completed
            $summary = $this->persistParsed($import, $parseResult, $source, $creditCardId);

            $this->usageLimits->increment($user, UsageLimitService::METRIC_UPLOADS);

            Log::info('statements.upload.completed', [
                'user_id' => (int) $import->user_id,
                'import_id' => (int) $import->id,
                'format' => (string) $import->format,
                'rows_total' => (int) $import->rows_total,
                'rows_imported' => (int) $import->rows_imported,
                'rows_updated' => (int) $summary->rowsUpdated,
                'rows_skipped' => (int) $import->rows_skipped,
                'checksum' => (string) $import->checksum,
            ]);

            return $summary;
        } catch (InvalidStatementException|UnsupportedStatementFormatException $e) {
            // Keep stored file for admin debug (retention still applies).
            $this->markFailed($import, $e->getMessage());

            if ($e instanceof InvalidStatementException) {
                throw $e->withImportId((int) $import->id);
            }

            throw $e;
        } catch (Throwable $e) {
            $this->markFailed($import, 'Falha ao processar o extrato.');

            throw $e;
        }
    }

    private function persistParsed(
        StatementImport $import,
        ParseResult $parseResult,
        string $source,
        ?int $creditCardOverrideId = null,
    ): UploadSummary {
        $user = User::query()->findOrFail((int) $import->user_id);
        $rowErrors = $parseResult->rowErrors;
        $prepared = $this->prepareRows(
            $import,
            $user,
            $parseResult->transactions,
            $source,
            $creditCardOverrideId,
        );

        $hashes = array_column($prepared['rows'], 'unique_hash');
        /** @var \Illuminate\Support\Collection<string, Transaction> $existingByHash */
        $existingByHash = $hashes === []
            ? collect()
            : Transaction::query()
                ->forUser((int) $import->user_id)
                ->whereIn('unique_hash', $hashes)
                ->get(['id', 'unique_hash', 'credit_card_id', 'category_id', 'description'])
                ->keyBy('unique_hash');

        $seenHashes = [];
        $newRows = [];
        $patches = [];
        $duplicateSkips = 0;
        $rowsUpdated = 0;

        foreach ($prepared['rows'] as $row) {
            $hash = (string) $row['unique_hash'];

            if (isset($seenHashes[$hash])) {
                continue;
            }
            $seenHashes[$hash] = true;

            $existing = $existingByHash->get($hash);
            if ($existing instanceof Transaction) {
                $patch = $this->buildReimportPatch($existing, $row);
                if ($patch === []) {
                    $duplicateSkips++;

                    continue;
                }

                $patches[(int) $existing->id] = $patch;
                $rowsUpdated++;

                continue;
            }

            $newRows[] = $row;
        }

        $intraBatchSkips = $prepared['intra_batch_skips'];
        $rowsSkipped = $duplicateSkips + $intraBatchSkips + count($rowErrors);
        $rowsImported = count($newRows);
        $rowsTotal = $parseResult->rowsTotal;
        $userId = (int) $import->user_id;

        DB::transaction(function () use (
            $import,
            $newRows,
            $patches,
            $userId,
            $rowsTotal,
            $rowsImported,
            $rowsSkipped,
        ): void {
            foreach (array_chunk($newRows, self::INSERT_CHUNK) as $chunk) {
                Transaction::query()->insert($chunk);
            }

            foreach ($patches as $transactionId => $patch) {
                Transaction::query()
                    ->forUser($userId)
                    ->whereKey($transactionId)
                    ->update($patch);
            }

            $import->update([
                'status' => StatementImport::STATUS_COMPLETED,
                'rows_total' => $rowsTotal,
                'rows_imported' => $rowsImported,
                'rows_skipped' => $rowsSkipped,
                'error_message' => null,
            ]);
        });

        $import->refresh();

        $this->syncInstallmentPlansAfterPersist($user, $newRows, array_keys($patches));

        return UploadSummary::fromImport($import, $rowErrors, $rowsUpdated);
    }

    /**
     * @param  list<array<string, mixed>>  $newRows
     * @param  list<int|string>  $patchedIds
     */
    private function syncInstallmentPlansAfterPersist(User $user, array $newRows, array $patchedIds): void
    {
        $ids = [];

        if ($newRows !== []) {
            $hashes = array_values(array_filter(array_map(
                static fn (array $row): ?string => isset($row['unique_hash']) ? (string) $row['unique_hash'] : null,
                $newRows,
            )));

            if ($hashes !== []) {
                $ids = array_merge(
                    $ids,
                    Transaction::query()
                        ->forUser($user)
                        ->whereIn('unique_hash', $hashes)
                        ->pluck('id')
                        ->all(),
                );
            }
        }

        foreach ($patchedIds as $id) {
            $ids[] = (int) $id;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return;
        }

        $transactions = Transaction::query()
            ->forUser($user)
            ->whereIn('id', $ids)
            ->where('type', 'debit')
            ->get();

        foreach ($transactions as $tx) {
            try {
                $this->installmentPlans->upsertFromTransaction($user, $tx);
            } catch (Throwable $e) {
                Log::warning('installment_plan.upsert_failed', [
                    'transaction_id' => $tx->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Safe reimport patch: link/relink card when provided; refresh alias display/category.
     * Never clears credit_card_id, never mutates amount/date/type/hash.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildReimportPatch(Transaction $existing, array $row): array
    {
        $patch = [];

        if ($row['credit_card_id'] !== null
            && (int) ($existing->credit_card_id ?? 0) !== (int) $row['credit_card_id']
        ) {
            $patch['credit_card_id'] = (int) $row['credit_card_id'];
        }

        $payload = json_decode((string) ($row['raw_payload'] ?? ''), true);
        $hadAlias = is_array($payload) && isset($payload['alias_id']);

        if ($hadAlias) {
            if ($row['category_id'] !== null
                && (int) ($existing->category_id ?? 0) !== (int) $row['category_id']
            ) {
                $patch['category_id'] = (int) $row['category_id'];
            }

            $description = (string) ($row['description'] ?? '');
            if ($description !== '' && $description !== (string) $existing->description) {
                $patch['description'] = $description;
            }
        }

        if ($patch === []) {
            return [];
        }

        $patch['updated_at'] = $row['updated_at'] ?? now();

        return $patch;
    }

    /**
     * @param  list<ParsedTransaction>  $transactions
     * @return array{rows: list<array<string, mixed>>, intra_batch_skips: int}
     */
    private function prepareRows(
        StatementImport $import,
        User $user,
        array $transactions,
        string $source,
        ?int $creditCardOverrideId = null,
    ): array {
        $now = now();
        $rows = [];
        $seen = [];
        $intraBatchSkips = 0;

        // Stamp card on fatura imports (default/override) or when UI sends an explicit card.
        $creditCardId = null;
        if ($import->format === StatementImport::FORMAT_CSV_CREDIT_CARD
            || $creditCardOverrideId !== null
        ) {
            $creditCardId = $this->creditCards->resolveForImport($user, $creditCardOverrideId);
        }

        foreach ($transactions as $tx) {
            $originalDescription = $tx->description;

            // Hash uses the raw bank description so re-imports stay idempotent
            // even after alias display_name rewrites the stored description.
            $hash = TransactionHasher::make(
                $tx->occurredOn,
                $tx->amount,
                $tx->type,
                $originalDescription,
                $tx->externalId,
                $source,
            );

            if (isset($seen[$hash])) {
                $intraBatchSkips++;

                continue;
            }
            $seen[$hash] = true;

            $match = $this->aliases->resolve($user, $originalDescription);
            $description = $match?->displayName ?? $originalDescription;
            $categoryId = $match?->categoryId;

            $payload = $tx->rawPayload;
            $payload['original_description'] = $originalDescription;
            if ($match !== null) {
                $payload['alias_id'] = $match->aliasId;
            }

            $rows[] = [
                'user_id' => (int) $import->user_id,
                'statement_import_id' => $import->id,
                'source_kind' => TransactionSourceKind::Import->value,
                'category_id' => $categoryId,
                'credit_card_id' => $creditCardId,
                'external_id' => $tx->externalId,
                'occurred_on' => $tx->occurredOn,
                'description' => $description,
                'amount' => $tx->amount,
                'type' => $tx->type,
                'unique_hash' => $hash,
                'raw_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return ['rows' => $rows, 'intra_batch_skips' => $intraBatchSkips];
    }

    private function markFailed(StatementImport $import, string $message): void
    {
        $errorMessage = $this->sanitizeErrorMessage($message);

        $import->update([
            'status' => StatementImport::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);

        Log::warning('statements.upload.failed', [
            'user_id' => (int) $import->user_id,
            'import_id' => (int) $import->id,
            'format' => (string) $import->format,
            'checksum' => $import->checksum !== null ? (string) $import->checksum : null,
            'error_message' => $errorMessage,
        ]);
    }

    private function sanitizeErrorMessage(string $message): string
    {
        $message = preg_replace('#[A-Za-z]:\\\\[^\s]+#', '[path]', $message) ?? $message;
        $message = preg_replace('#/(?:home[^/\s]*|var|tmp|Users)/[^\s]+#', '[path]', $message) ?? $message;
        $message = trim($message);

        if ($message === '') {
            $message = 'Não foi possível processar o extrato.';
        }

        return mb_substr($message, 0, self::ERROR_MESSAGE_MAX);
    }
}
