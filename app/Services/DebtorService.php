<?php

namespace App\Services;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Debtor;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro de pessoas (devedores) e resolução de cobrança ao vincular lançamentos.
 */
final class DebtorService
{
    /**
     * @param  array{q?: string|null}  $filters
     * @return Builder<Debtor>
     */
    public function queryForUser(User $user, array $filters = []): Builder
    {
        $query = Debtor::query()
            ->forUser($user)
            ->select('debtors.*')
            ->withCount([
                'loans as open_loans_count' => function (Builder $q): void {
                    $q->whereIn('status', [LoanStatus::Open->value, LoanStatus::Partial->value]);
                },
            ])
            ->addSelect([
                'open_remaining_total' => Loan::query()
                    ->selectRaw('COALESCE(SUM(GREATEST(amount - paid_amount, 0)), 0)')
                    ->whereColumn('loans.debtor_id', 'debtors.id')
                    ->whereIn('status', [LoanStatus::Open->value, LoanStatus::Partial->value]),
            ])
            ->orderBy('name')
            ->orderBy('id');

        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('name', 'like', $like);
        }

        return $query;
    }

    /**
     * @param  array{name: string, notes?: string|null}  $data
     */
    public function create(User $user, array $data): Debtor
    {
        $name = trim((string) $data['name']);

        $exists = Debtor::query()
            ->forUser($user)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Você já tem uma pessoa com este nome.'],
            ]);
        }

        return Debtor::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'notes' => $this->nullableString($data['notes'] ?? null),
        ]);
    }

    /**
     * @param  array{name?: string, notes?: string|null}  $data
     */
    public function update(User $user, Debtor $debtor, array $data): Debtor
    {
        $this->assertOwner($user, $debtor);

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            $dup = Debtor::query()
                ->forUser($user)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->where('id', '!=', $debtor->id)
                ->exists();

            if ($dup) {
                throw ValidationException::withMessages([
                    'name' => ['Você já tem uma pessoa com este nome.'],
                ]);
            }

            $debtor->name = $name;

            // Keep denormalized loan names in sync.
            Loan::query()
                ->where('debtor_id', $debtor->id)
                ->update(['debtor_name' => $name]);
        }

        if (array_key_exists('notes', $data)) {
            $debtor->notes = $this->nullableString($data['notes']);
        }

        $debtor->save();

        return $debtor->fresh();
    }

    public function delete(User $user, Debtor $debtor): void
    {
        $this->assertOwner($user, $debtor);

        $hasOpen = $debtor->loans()
            ->whereIn('status', [LoanStatus::Open, LoanStatus::Partial])
            ->exists();

        if ($hasOpen) {
            throw ValidationException::withMessages([
                'debtor' => ['Não é possível excluir: há cobranças em aberto para esta pessoa.'],
            ]);
        }

        DB::transaction(function () use ($debtor): void {
            Loan::query()
                ->where('debtor_id', $debtor->id)
                ->update(['debtor_id' => null]);

            $debtor->delete();
        });
    }

    /**
     * Find by id (owned) or find/create by name.
     */
    public function resolve(User $user, ?int $debtorId = null, ?string $name = null): Debtor
    {
        if ($debtorId !== null) {
            $debtor = Debtor::query()->forUser($user)->whereKey($debtorId)->first();
            if ($debtor === null) {
                throw ValidationException::withMessages([
                    'debtor_id' => ['Pessoa inválida ou não pertence a você.'],
                ]);
            }

            return $debtor;
        }

        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'debtor_name' => ['Informe o nome da pessoa.'],
            ]);
        }

        $existing = Debtor::query()
            ->forUser($user)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmed)])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Debtor::query()->create([
            'user_id' => $user->id,
            'name' => $trimmed,
            'notes' => null,
        ]);
    }

    /**
     * Prefer an open/partial loan for the debtor; otherwise create one from the transaction context.
     * Used when a single manual transaction is tagged with a debtor (not bulk link).
     *
     * @param  array{
     *     amount: numeric,
     *     occurred_on: string,
     *     credit_card_id?: int|null,
     *     description?: string|null
     * }  $tx
     */
    public function findOpenLoanOrCreateFromTransaction(User $user, Debtor $debtor, array $tx): Loan
    {
        $this->assertOwner($user, $debtor);

        $open = Loan::query()
            ->forUser($user)
            ->where('debtor_id', $debtor->id)
            ->whereIn('status', [LoanStatus::Open, LoanStatus::Partial])
            ->orderBy('due_on')
            ->orderBy('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        return $this->createLoanFromTransaction($user, $debtor, $tx);
    }

    /**
     * Always create a new open loan from a transaction (bulk link = one debt per saída).
     *
     * @param  array{
     *     amount: numeric,
     *     occurred_on: string,
     *     credit_card_id?: int|null,
     *     description?: string|null
     * }  $tx
     */
    public function createLoanFromTransaction(User $user, Debtor $debtor, array $tx): Loan
    {
        $this->assertOwner($user, $debtor);

        $creditCardId = isset($tx['credit_card_id']) && $tx['credit_card_id'] !== null
            ? (int) $tx['credit_card_id']
            : null;

        $kind = $creditCardId !== null ? LoanKind::CardLimit : LoanKind::Cash;

        if ($kind === LoanKind::CardLimit) {
            $owns = CreditCard::query()
                ->whereKey($creditCardId)
                ->where('user_id', $user->id)
                ->exists();

            if (! $owns) {
                throw ValidationException::withMessages([
                    'credit_card_id' => ['Cartão inválido ou não pertence a você.'],
                ]);
            }
        } else {
            $creditCardId = null;
        }

        $occurredOn = (string) $tx['occurred_on'];
        $description = isset($tx['description']) && is_string($tx['description']) && $tx['description'] !== ''
            ? $tx['description']
            : null;

        return Loan::query()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtor->id,
            'debtor_name' => $debtor->name,
            'credit_card_id' => $creditCardId,
            'kind' => $kind,
            'amount' => number_format((float) $tx['amount'], 2, '.', ''),
            'currency' => 'BRL',
            'lent_on' => $occurredOn,
            'due_on' => $occurredOn,
            'status' => LoanStatus::Open,
            'paid_amount' => '0.00',
            'paid_at' => null,
            'notes' => $description !== null
                ? 'Criado ao vincular lançamento: '.$description
                : 'Criado ao vincular lançamento.',
        ]);
    }

    /**
     * Bulk-link debit transactions to this debtor (one new loan per saída).
     *
     * @param  list<int>  $transactionIds
     * @return array{linked: int, skipped: int, limit: int}
     */
    public function linkTransactions(User $user, Debtor $debtor, array $transactionIds): array
    {
        $this->assertOwner($user, $debtor);

        $limit = 100;
        $ids = array_values(array_unique(array_map('intval', $transactionIds)));
        $ids = array_slice($ids, 0, $limit);

        if ($ids === []) {
            return ['linked' => 0, 'skipped' => 0, 'limit' => $limit];
        }

        $linked = 0;
        $skipped = 0;

        DB::transaction(function () use ($user, $debtor, $ids, &$linked, &$skipped): void {
            $rows = Transaction::query()
                ->forUser($user)
                ->whereIn('id', $ids)
                ->with('loan:id,debtor_id')
                ->get([
                    'id',
                    'user_id',
                    'loan_id',
                    'type',
                    'amount',
                    'occurred_on',
                    'description',
                    'credit_card_id',
                ]);

            $found = $rows->keyBy('id');

            foreach ($ids as $id) {
                $tx = $found->get($id);
                if ($tx === null) {
                    $skipped++;
                    continue;
                }

                if ((string) $tx->type !== 'debit') {
                    $skipped++;
                    continue;
                }

                if (
                    $tx->loan !== null
                    && (int) ($tx->loan->debtor_id ?? 0) === (int) $debtor->id
                ) {
                    $skipped++;
                    continue;
                }

                $loan = $this->createLoanFromTransaction($user, $debtor, [
                    'amount' => $tx->amount,
                    'occurred_on' => $tx->occurred_on?->format('Y-m-d') ?? (string) $tx->occurred_on,
                    'credit_card_id' => $tx->credit_card_id,
                    'description' => is_string($tx->description) ? $tx->description : null,
                ]);

                $tx->loan_id = $loan->id;
                $tx->save();
                $linked++;
            }
        });

        return [
            'linked' => $linked,
            'skipped' => $skipped,
            'limit' => $limit,
        ];
    }

    private function assertOwner(User $user, Debtor $debtor): void
    {
        if ((int) $debtor->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'debtor' => ['Pessoa não pertence ao usuário autenticado.'],
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
