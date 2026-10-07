<?php

namespace App\Services;

use App\Enums\TransactionSourceKind;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionHasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manual transaction CRUD (PLAN_EXPANSAO §3.1 / §3.2).
 *
 * Hash source marker is `manual:{userId}` so manual rows never collide with
 * import hashes. Quota increment happens only after a successful create.
 *
 * After create/update/delete, linked goals are refreshed via GoalProgressService.
 *
 * Deleting an imported row removes only the transaction line — statement_imports
 * counters are historical and are NOT rewritten.
 */
final class ManualTransactionService
{
    public function __construct(
        private readonly UsageLimitService $usageLimits,
        private readonly GoalProgressService $goalProgress,
        private readonly AliasResolutionService $aliases,
        private readonly DebtorService $debtors,
    ) {}

    /**
     * @param  array{
     *     occurred_on: string,
     *     amount: numeric,
     *     type: string,
     *     description: string,
     *     category_id?: int|null,
     *     notes?: string|null,
     *     credit_card_id?: int|null,
     *     loan_id?: int|null,
     *     debtor_id?: int|null
     * }  $data
     */
    public function create(User $user, array $data): Transaction
    {
        $data = $this->resolveDebtorLoanLink($user, $data);
        $resolved = $this->resolveWithAlias($user, $data);
        $hash = $this->hashForManual($user, [
            'occurred_on' => $resolved['occurred_on'],
            'amount' => $resolved['amount'],
            'type' => $resolved['type'],
            'description' => $resolved['original_description'],
        ]);
        $this->assertUniqueForUser($user, $hash);

        $transaction = DB::transaction(function () use ($user, $resolved, $hash, $data): Transaction {
            $payload = [
                'origin' => 'manual',
                'notes' => $resolved['notes'],
                'original_description' => $resolved['original_description'],
            ];
            if ($resolved['alias_id'] !== null) {
                $payload['alias_id'] = $resolved['alias_id'];
            }

            return Transaction::query()->create([
                'user_id' => $user->id,
                'statement_import_id' => null,
                'source_kind' => TransactionSourceKind::Manual,
                'category_id' => $resolved['category_id'],
                'credit_card_id' => $data['credit_card_id'] ?? null,
                'loan_id' => $data['loan_id'] ?? null,
                'external_id' => null,
                'occurred_on' => $resolved['occurred_on'],
                'description' => $resolved['description'],
                'amount' => number_format((float) $resolved['amount'], 2, '.', ''),
                'type' => $resolved['type'],
                'unique_hash' => $hash,
                'raw_payload' => $payload,
            ]);
        });

        $this->usageLimits->increment($user, UsageLimitService::METRIC_MANUAL_TRANSACTIONS);
        $this->goalProgress->touchFromTransaction($transaction);

        return $transaction->load(['category', 'creditCard:id,name', 'loan:id,debtor_name,status']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Transaction $transaction, array $data): Transaction
    {
        $data = $this->resolveDebtorLoanLink($actor, $data, $transaction);

        if ($transaction->source_kind === TransactionSourceKind::Import) {
            $updated = $this->updateImported($actor, $transaction, $data);
        } else {
            $updated = $this->updateManual($actor, $transaction, $data);
        }

        $this->goalProgress->touchFromTransaction($updated);

        return $updated;
    }

    public function delete(Transaction $transaction): void
    {
        $userId = (int) $transaction->user_id;

        // Hard delete. Import counters are not adjusted (historical snapshot).
        $transaction->delete();

        $this->goalProgress->recalculateLinkedForUser($userId);
    }

    /**
     * Hard-delete every transaction for the user. Import counters are not rewritten.
     *
     * @return int Number of rows deleted
     */
    public function wipeAllForUser(User $user): int
    {
        return (int) DB::transaction(function () use ($user): int {
            $deleted = Transaction::query()
                ->where('user_id', $user->id)
                ->delete();

            $this->goalProgress->recalculateLinkedForUser((int) $user->id);

            return (int) $deleted;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateManual(User $actor, Transaction $transaction, array $data): Transaction
    {
        $merged = [
            'occurred_on' => $data['occurred_on'] ?? $transaction->occurred_on?->format('Y-m-d'),
            'amount' => $data['amount'] ?? $transaction->amount,
            'type' => $data['type'] ?? $transaction->type,
            'description' => $data['description']
                ?? ($transaction->raw_payload['original_description'] ?? $transaction->description),
            'category_id' => array_key_exists('category_id', $data)
                ? $data['category_id']
                : $transaction->category_id,
            'notes' => array_key_exists('notes', $data)
                ? $data['notes']
                : ($transaction->raw_payload['notes'] ?? null),
        ];

        // Re-apply aliases when description is present (new or carried original).
        $categoryProvided = array_key_exists('category_id', $data);
        if (! $categoryProvided) {
            unset($merged['category_id']);
        }
        $resolved = $this->resolveWithAlias($actor, $merged);
        if ($categoryProvided) {
            $resolved['category_id'] = $data['category_id'];
        }

        $hash = $this->hashForManual($actor, [
            'occurred_on' => $resolved['occurred_on'],
            'amount' => $resolved['amount'],
            'type' => $resolved['type'],
            'description' => $resolved['original_description'],
        ]);
        $this->assertUniqueForUser($actor, $hash, $transaction->id);

        $payload = is_array($transaction->raw_payload) ? $transaction->raw_payload : [];
        $payload['origin'] = 'manual';
        $payload['notes'] = $resolved['notes'];
        $payload['original_description'] = $resolved['original_description'];
        if ($resolved['alias_id'] !== null) {
            $payload['alias_id'] = $resolved['alias_id'];
        } else {
            unset($payload['alias_id']);
        }

        $transaction->fill([
            'occurred_on' => $resolved['occurred_on'],
            'amount' => number_format((float) $resolved['amount'], 2, '.', ''),
            'type' => $resolved['type'],
            'description' => $resolved['description'],
            'category_id' => $resolved['category_id'],
            'unique_hash' => $hash,
            'raw_payload' => $payload,
        ]);

        if (array_key_exists('credit_card_id', $data)) {
            $transaction->credit_card_id = $data['credit_card_id'];
        }
        if (array_key_exists('loan_id', $data)) {
            $transaction->loan_id = $data['loan_id'];
        }

        $transaction->save();

        return $transaction->fresh()->load(['category', 'creditCard:id,name', 'loan:id,debtor_name,status']);
    }

    /**
     * Auto-apply first matching alias: rewrite display description; fill category
     * only when the caller did not supply one.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     occurred_on: mixed,
     *     amount: mixed,
     *     type: mixed,
     *     description: string,
     *     category_id: int|null,
     *     notes: mixed,
     *     original_description: string,
     *     alias_id: int|null
     * }
     */
    private function resolveWithAlias(User $user, array $data): array
    {
        $original = (string) $data['description'];
        $match = $this->aliases->resolve($user, $original);

        $categoryId = array_key_exists('category_id', $data)
            ? $data['category_id']
            : null;

        if ($categoryId === null && $match?->categoryId !== null) {
            $categoryId = $match->categoryId;
        }

        return [
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'type' => $data['type'],
            'description' => $match?->displayName ?? $original,
            'category_id' => $categoryId !== null ? (int) $categoryId : null,
            'notes' => $data['notes'] ?? null,
            'original_description' => $original,
            'alias_id' => $match?->aliasId,
        ];
    }

    /**
     * Imported rows: non-admin may only change category_id (+ notes in payload).
     * Admin may also change amount / occurred_on / description / type.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateImported(User $actor, Transaction $transaction, array $data): Transaction
    {
        $allowed = ['category_id', 'notes', 'credit_card_id', 'loan_id', 'debtor_id'];

        if ($actor->isAdmin()) {
            $allowed = array_merge($allowed, [
                'occurred_on',
                'amount',
                'type',
                'description',
            ]);
        }

        $forbidden = array_diff(array_keys($data), $allowed);
        if ($forbidden !== []) {
            throw ValidationException::withMessages([
                array_values($forbidden)[0] => 'Lançamentos importados só permitem alterar categoria'
                    .($actor->isAdmin() ? ' (Admin: também amount/occurred_on/description/type).' : '.')
                    .' Campos bloqueados: '.implode(', ', $forbidden).'.',
            ]);
        }

        if (array_key_exists('category_id', $data)) {
            $transaction->category_id = $data['category_id'];
        }

        if (array_key_exists('credit_card_id', $data)) {
            $transaction->credit_card_id = $data['credit_card_id'];
        }

        if (array_key_exists('loan_id', $data)) {
            $transaction->loan_id = $data['loan_id'];
        }

        if (array_key_exists('notes', $data)) {
            $payload = is_array($transaction->raw_payload) ? $transaction->raw_payload : [];
            $payload['notes'] = $data['notes'];
            $transaction->raw_payload = $payload;
        }

        if ($actor->isAdmin()) {
            foreach (['occurred_on', 'amount', 'type', 'description'] as $field) {
                if (array_key_exists($field, $data)) {
                    $transaction->{$field} = $field === 'amount'
                        ? number_format((float) $data[$field], 2, '.', '')
                        : $data[$field];
                }
            }

            // Recalculate hash when identity fields change (keep original import source marker if present).
            if ($transaction->isDirty(['occurred_on', 'amount', 'type', 'description'])) {
                $source = is_array($transaction->raw_payload)
                    ? (string) ($transaction->raw_payload['source'] ?? 'nubank')
                    : 'nubank';

                $hash = TransactionHasher::make(
                    $transaction->occurred_on?->format('Y-m-d') ?? (string) $transaction->occurred_on,
                    $transaction->amount,
                    (string) $transaction->type,
                    (string) $transaction->description,
                    $transaction->external_id,
                    $source,
                );
                $this->assertUniqueForUser($actor, $hash, $transaction->id);
                $transaction->unique_hash = $hash;
            }
        }

        $transaction->save();

        return $transaction->fresh()->load(['category', 'creditCard:id,name', 'loan:id,debtor_name,status']);
    }

    /**
     * @param  array{occurred_on: string, amount: mixed, type: string, description: string}  $data
     */
    private function hashForManual(User $user, array $data): string
    {
        return TransactionHasher::make(
            (string) $data['occurred_on'],
            $data['amount'],
            (string) $data['type'],
            (string) $data['description'],
            null,
            'manual:'.$user->id,
        );
    }

    private function assertUniqueForUser(User $user, string $hash, ?int $ignoreId = null): void
    {
        $query = Transaction::query()
            ->where('user_id', $user->id)
            ->where('unique_hash', $hash);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'description' => 'Já existe um lançamento idêntico para este usuário neste período.',
            ]);
        }
    }

    /**
     * When debtor_id is set without loan_id, reuse an open loan or create one from the tx.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveDebtorLoanLink(User $user, array $data, ?Transaction $existing = null): array
    {
        $hasDebtor = array_key_exists('debtor_id', $data) && $data['debtor_id'] !== null && $data['debtor_id'] !== '';
        $hasLoan = array_key_exists('loan_id', $data)
            ? ($data['loan_id'] !== null && $data['loan_id'] !== '')
            : ($existing?->loan_id !== null);

        if (! $hasDebtor || $hasLoan) {
            return $data;
        }

        $debtor = $this->debtors->resolve($user, (int) $data['debtor_id'], null);

        $amount = $data['amount'] ?? $existing?->amount;
        $occurredOn = $data['occurred_on'] ?? $existing?->occurred_on?->format('Y-m-d');
        $description = $data['description'] ?? $existing?->description;
        $creditCardId = array_key_exists('credit_card_id', $data)
            ? $data['credit_card_id']
            : $existing?->credit_card_id;

        if ($amount === null || $occurredOn === null) {
            throw ValidationException::withMessages([
                'debtor_id' => ['Não foi possível vincular a pessoa sem valor e data do lançamento.'],
            ]);
        }

        $loan = $this->debtors->findOpenLoanOrCreateFromTransaction($user, $debtor, [
            'amount' => $amount,
            'occurred_on' => (string) $occurredOn,
            'credit_card_id' => $creditCardId,
            'description' => is_string($description) ? $description : null,
        ]);

        $data['loan_id'] = (int) $loan->id;

        return $data;
    }
}
