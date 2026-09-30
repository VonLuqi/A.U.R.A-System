<?php

namespace App\Services;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loans / cobranças CRUD (PLAN_CARTOES_EMPRESTIMOS §3.2).
 */
final class LoanService
{
    public function __construct(
        private readonly UsageLimitService $usageLimits,
        private readonly ManualTransactionService $manualTransactions,
        private readonly DebtorService $debtors,
    ) {}

    /**
     * @param  array{
     *     status?: string|null,
     *     kind?: string|null,
     *     due_from?: string|null,
     *     due_to?: string|null,
     *     q?: string|null,
     *     overdue?: bool|null,
     *     debtor_id?: int|null,
     *     collectible?: bool|null
     * }  $filters
     * @return Builder<Loan>
     */
    public function queryForUser(User $user, array $filters = []): Builder
    {
        $query = Loan::query()
            ->forUser($user)
            ->with(['creditCard:id,name', 'debtor:id,name', 'installmentItem:id,loan_id,installment_plan_id'])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'partial' THEN 1 WHEN 'paid' THEN 2 ELSE 3 END")
            ->orderBy('due_on')
            ->orderBy('id');

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '' && in_array($status, LoanStatus::values(), true)) {
            $query->where('status', $status);
        } elseif (! empty($filters['collectible'])) {
            $query->whereIn('status', [LoanStatus::Open, LoanStatus::Partial]);
        }

        $kind = $filters['kind'] ?? null;
        if (is_string($kind) && $kind !== '' && in_array($kind, LoanKind::values(), true)) {
            $query->where('kind', $kind);
        }

        $dueFrom = $filters['due_from'] ?? null;
        $dueTo = $filters['due_to'] ?? null;
        if (is_string($dueFrom) && $dueFrom !== '') {
            $query->whereDate('due_on', '>=', $dueFrom);
        }
        if (is_string($dueTo) && $dueTo !== '') {
            $query->whereDate('due_on', '<=', $dueTo);
        }

        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('debtor_name', 'like', $like);
        }

        $debtorId = $filters['debtor_id'] ?? null;
        if ($debtorId !== null) {
            $query->where('debtor_id', (int) $debtorId);
        }

        if (! empty($filters['overdue'])) {
            $tz = (string) config('app.timezone', 'America/Sao_Paulo');
            $today = Carbon::now($tz)->toDateString();
            $query->whereIn('status', [LoanStatus::Open, LoanStatus::Partial])
                ->whereDate('due_on', '<', $today);
        }

        return $query;
    }

    /**
     * @param  array{
     *     debtor_name?: string|null,
     *     debtor_id?: int|null,
     *     kind: string,
     *     credit_card_id?: int|null,
     *     amount: numeric,
     *     currency?: string|null,
     *     lent_on: string,
     *     due_on: string,
     *     notes?: string|null,
     *     create_expense?: bool,
     *     expense_description?: string|null,
     *     expense_amount?: numeric|null,
     *     expense_occurred_on?: string|null,
     *     expense_category_id?: int|null
     * }  $data
     */
    public function create(User $user, array $data): Loan
    {
        $this->usageLimits->assertCanCreateLoan($user);
        $attrs = $this->normalize($user, $data, creating: true);

        $createExpense = (bool) ($data['create_expense'] ?? false);
        if ($createExpense) {
            if (! config('aura.features.manual_transactions', true)) {
                throw ValidationException::withMessages([
                    'create_expense' => ['Criação de saídas manuais está temporariamente desabilitada.'],
                ]);
            }

            $this->usageLimits->assertCan($user, UsageLimitService::METRIC_MANUAL_TRANSACTIONS);
        }

        return DB::transaction(function () use ($user, $attrs, $data, $createExpense): Loan {
            $loan = Loan::query()->create([
                'user_id' => $user->id,
                ...$attrs,
            ]);

            if ($createExpense) {
                $description = trim((string) ($data['expense_description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages([
                        'expense_description' => ['Informe o que foi comprado / a descrição da saída.'],
                    ]);
                }

                $expenseAmount = array_key_exists('expense_amount', $data) && $data['expense_amount'] !== null && $data['expense_amount'] !== ''
                    ? $data['expense_amount']
                    : $attrs['amount'];

                $occurredOn = ! empty($data['expense_occurred_on'])
                    ? (string) $data['expense_occurred_on']
                    : (string) $attrs['lent_on'];

                $categoryId = array_key_exists('expense_category_id', $data) && $data['expense_category_id'] !== null
                    ? (int) $data['expense_category_id']
                    : null;

                $this->manualTransactions->create($user, [
                    'occurred_on' => $occurredOn,
                    'amount' => $expenseAmount,
                    'type' => 'debit',
                    'description' => $description,
                    'category_id' => $categoryId,
                    'loan_id' => (int) $loan->id,
                    'credit_card_id' => $attrs['credit_card_id'],
                    'notes' => null,
                ]);
            }

            return $loan->load(['creditCard:id,name', 'debtor:id,name']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Loan $loan, array $data): Loan
    {
        $this->assertOwner($user, $loan);

        if (in_array($loan->status, [LoanStatus::Paid, LoanStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'loan' => ['Cobranças pagas ou canceladas não podem ser editadas.'],
            ]);
        }

        $attrs = $this->normalize($user, array_merge([
            'kind' => $loan->kind instanceof LoanKind ? $loan->kind->value : (string) $loan->kind,
            'credit_card_id' => $loan->credit_card_id,
            'debtor_id' => $loan->debtor_id,
            'debtor_name' => $loan->debtor_name,
            'lent_on' => $loan->lent_on?->format('Y-m-d'),
            'due_on' => $loan->due_on?->format('Y-m-d'),
            'amount' => $loan->amount,
        ], $data), creating: false);

        return DB::transaction(function () use ($loan, $attrs): Loan {
            $loan->fill($attrs)->save();

            return $loan->fresh(['creditCard:id,name', 'debtor:id,name']);
        });
    }

    public function markPaid(User $user, Loan $loan, ?float $paidAmount = null): Loan
    {
        $this->assertOwner($user, $loan);

        if ($loan->status === LoanStatus::Paid) {
            throw ValidationException::withMessages([
                'loan' => ['Esta cobrança já está paga.'],
            ]);
        }

        if ($loan->status === LoanStatus::Cancelled) {
            throw ValidationException::withMessages([
                'loan' => ['Não é possível pagar uma cobrança cancelada.'],
            ]);
        }

        return DB::transaction(function () use ($loan, $paidAmount): Loan {
            $loan->markPaid($paidAmount);

            return $loan->fresh(['creditCard:id,name', 'debtor:id,name']);
        });
    }

    public function cancel(User $user, Loan $loan): Loan
    {
        $this->assertOwner($user, $loan);

        if ($loan->status === LoanStatus::Paid) {
            throw ValidationException::withMessages([
                'loan' => ['Cobranças pagas não podem ser canceladas.'],
            ]);
        }

        if ($loan->status === LoanStatus::Cancelled) {
            return $loan->loadMissing(['creditCard:id,name', 'debtor:id,name']);
        }

        return DB::transaction(function () use ($loan): Loan {
            $loan->forceFill([
                'status' => LoanStatus::Cancelled,
            ])->save();

            return $loan->fresh(['creditCard:id,name', 'debtor:id,name']);
        });
    }

    public function delete(User $user, Loan $loan): void
    {
        $this->assertOwner($user, $loan);

        $status = $loan->status;
        if (! in_array($status, [LoanStatus::Open, LoanStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'loan' => ['Só é possível excluir cobranças em aberto ou canceladas. Use cancelar ou marcar como paga.'],
            ]);
        }

        if ($loan->transactions()->exists()) {
            throw ValidationException::withMessages([
                'loan' => ['Há lançamentos vinculados. Cancele a cobrança em vez de excluir.'],
            ]);
        }

        $loan->delete();
    }

    private function assertOwner(User $user, Loan $loan): void
    {
        if ((int) $loan->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'loan' => ['Cobrança não pertence ao usuário autenticado.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(User $user, array $data, bool $creating): array
    {
        $kind = LoanKind::from((string) $data['kind']);
        $creditCardId = array_key_exists('credit_card_id', $data)
            ? ($data['credit_card_id'] !== null ? (int) $data['credit_card_id'] : null)
            : null;

        if ($kind === LoanKind::CardLimit) {
            if ($creditCardId === null) {
                throw ValidationException::withMessages([
                    'credit_card_id' => ['Informe o cartão quando o tipo for limite do cartão.'],
                ]);
            }

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

        $debtorId = array_key_exists('debtor_id', $data) && $data['debtor_id'] !== null
            ? (int) $data['debtor_id']
            : null;
        $debtorName = array_key_exists('debtor_name', $data) ? $data['debtor_name'] : null;

        $debtor = $this->debtors->resolve(
            $user,
            $debtorId,
            is_string($debtorName) ? $debtorName : null,
        );

        $out = [
            'debtor_id' => $debtor->id,
            'debtor_name' => $debtor->name,
            'kind' => $kind,
            'credit_card_id' => $creditCardId,
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => strtoupper((string) ($data['currency'] ?? 'BRL')),
            'lent_on' => (string) $data['lent_on'],
            'due_on' => (string) $data['due_on'],
            'notes' => isset($data['notes']) && is_string($data['notes']) && $data['notes'] !== ''
                ? $data['notes']
                : null,
        ];

        if ($creating) {
            $out['status'] = LoanStatus::Open;
            $out['paid_amount'] = '0.00';
            $out['paid_at'] = null;
        }

        return $out;
    }
}
