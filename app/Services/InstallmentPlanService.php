<?php

namespace App\Services;

use App\Enums\InstallmentItemStatus;
use App\Enums\InstallmentPlanStatus;
use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Models\CreditCard;
use App\Models\Debtor;
use App\Models\InstallmentItem;
use App\Models\InstallmentPlan;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\User;
use App\Support\InstallmentTitleParser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parcelamentos: upsert a partir de saídas X/Y, CRUD manual e sync de loans.
 */
final class InstallmentPlanService
{
    /**
     * @param  array{q?: string|null, status?: string|null, debtor_id?: int|null, credit_card_id?: int|null}  $filters
     * @return Builder<InstallmentPlan>
     */
    public function queryForUser(User $user, array $filters = []): Builder
    {
        $query = InstallmentPlan::query()
            ->forUser($user)
            ->with([
                'creditCard:id,name',
                'debtor:id,name',
                'items',
            ])
            ->orderByDesc('id');

        $q = $filters['q'] ?? null;
        if (is_string($q) && $q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('title', 'like', $like);
        }

        $status = $filters['status'] ?? null;
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        if (isset($filters['debtor_id']) && $filters['debtor_id'] !== null) {
            $query->where('debtor_id', (int) $filters['debtor_id']);
        }

        if (isset($filters['credit_card_id']) && $filters['credit_card_id'] !== null) {
            $query->where('credit_card_id', (int) $filters['credit_card_id']);
        }

        return $query;
    }

    /**
     * Create / link installment plan from a debit transaction with X/Y in the title.
     */
    public function upsertFromTransaction(User $user, Transaction $tx): ?InstallmentPlan
    {
        if ((string) $tx->type !== 'debit') {
            return null;
        }

        $parsed = InstallmentTitleParser::parse(
            is_string($tx->description) ? $tx->description : null,
        );

        if ($parsed === null) {
            return null;
        }

        // Already linked to an item — refresh amount/due and return.
        $existingItem = InstallmentItem::query()
            ->where('transaction_id', $tx->id)
            ->with('plan')
            ->first();

        if ($existingItem !== null) {
            $this->applyTransactionToItem($existingItem, $tx);
            $plan = $existingItem->plan;
            if ($plan !== null) {
                $this->refreshPlanStatus($plan);
            }

            return $plan?->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
        }

        $amount = number_format(abs((float) $tx->amount), 2, '.', '');
        $creditCardId = $tx->credit_card_id !== null ? (int) $tx->credit_card_id : null;
        $occurredOn = $tx->occurred_on?->format('Y-m-d') ?? (string) $tx->occurred_on;

        return DB::transaction(function () use (
            $user,
            $tx,
            $parsed,
            $amount,
            $creditCardId,
            $occurredOn,
        ): InstallmentPlan {
            $plan = $this->findMatchingPlan(
                $user,
                $parsed['title'],
                $parsed['total'],
                $amount,
                $creditCardId,
            );

            if ($plan === null) {
                $plan = InstallmentPlan::query()->create([
                    'user_id' => $user->id,
                    'credit_card_id' => $creditCardId,
                    'debtor_id' => null,
                    'title' => $parsed['title'],
                    'total_count' => $parsed['total'],
                    'installment_amount' => $amount,
                    'currency' => 'BRL',
                    'status' => InstallmentPlanStatus::Open,
                    'notes' => null,
                ]);

                $this->createItemsForNewPlan(
                    $plan,
                    $parsed['current'],
                    $parsed['total'],
                    $amount,
                    $occurredOn,
                    $tx,
                );
            } else {
                $item = $plan->items()->where('number', $parsed['current'])->first();
                if ($item === null) {
                    throw ValidationException::withMessages([
                        'installment' => ['Parcela inválida no plano.'],
                    ]);
                }

                // Don't steal a transaction already on another item.
                if ($item->transaction_id !== null && (int) $item->transaction_id !== (int) $tx->id) {
                    // Different tx already on this slot — leave as-is.
                } else {
                    $this->applyTransactionToItem($item, $tx, preservePaidStatus: true);
                }
            }

            $this->refreshPlanStatus($plan);

            if ($plan->debtor_id !== null) {
                $this->syncLoansForPlan($user, $plan->fresh(['items', 'debtor']));
            }

            return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
        });
    }

    /**
     * Backfill plans for existing debit transactions that look like installments.
     *
     * @return int Number of transactions processed into plans
     */
    public function backfillForUser(User $user, int $limit = 500): int
    {
        $processed = 0;

        $alreadyLinked = InstallmentItem::query()
            ->whereNotNull('transaction_id')
            ->whereHas('plan', fn (Builder $q) => $q->where('user_id', $user->id))
            ->pluck('transaction_id')
            ->all();

        $query = Transaction::query()
            ->forUser($user)
            ->where('type', 'debit')
            ->whereNotNull('description')
            ->orderBy('id');

        if ($alreadyLinked !== []) {
            $query->whereNotIn('id', $alreadyLinked);
        }

        $txs = $query->limit($limit)->get();

        foreach ($txs as $tx) {
            if (InstallmentTitleParser::parse((string) $tx->description) === null) {
                continue;
            }

            $this->upsertFromTransaction($user, $tx);
            $processed++;
        }

        return $processed;
    }

    /**
     * @param  array{
     *     title: string,
     *     total_count: int,
     *     installment_amount: numeric,
     *     first_due_on: string,
     *     credit_card_id?: int|null,
     *     debtor_id?: int|null,
     *     notes?: string|null,
     *     paid_numbers?: list<int>
     * }  $data
     */
    public function createManual(User $user, array $data): InstallmentPlan
    {
        $title = trim((string) $data['title']);
        $total = (int) $data['total_count'];
        $amount = number_format((float) $data['installment_amount'], 2, '.', '');
        $firstDue = (string) $data['first_due_on'];
        $creditCardId = isset($data['credit_card_id']) && $data['credit_card_id'] !== null
            ? (int) $data['credit_card_id']
            : null;
        $debtorId = isset($data['debtor_id']) && $data['debtor_id'] !== null
            ? (int) $data['debtor_id']
            : null;
        /** @var list<int> $paidNumbers */
        $paidNumbers = array_values(array_unique(array_map(
            'intval',
            $data['paid_numbers'] ?? [],
        )));

        if ($creditCardId !== null) {
            $owns = CreditCard::query()
                ->whereKey($creditCardId)
                ->where('user_id', $user->id)
                ->exists();
            if (! $owns) {
                throw ValidationException::withMessages([
                    'credit_card_id' => ['Cartão inválido ou não pertence a você.'],
                ]);
            }
        }

        $debtor = null;
        if ($debtorId !== null) {
            $debtor = Debtor::query()->forUser($user)->whereKey($debtorId)->first();
            if ($debtor === null) {
                throw ValidationException::withMessages([
                    'debtor_id' => ['Pessoa inválida ou não pertence a você.'],
                ]);
            }
        }

        foreach ($paidNumbers as $n) {
            if ($n < 1 || $n > $total) {
                throw ValidationException::withMessages([
                    'paid_numbers' => ['Número de parcela paga inválido.'],
                ]);
            }
        }

        return DB::transaction(function () use (
            $user,
            $title,
            $total,
            $amount,
            $firstDue,
            $creditCardId,
            $debtor,
            $paidNumbers,
            $data,
        ): InstallmentPlan {
            $plan = InstallmentPlan::query()->create([
                'user_id' => $user->id,
                'credit_card_id' => $creditCardId,
                'debtor_id' => $debtor?->id,
                'title' => $title,
                'total_count' => $total,
                'installment_amount' => $amount,
                'currency' => 'BRL',
                'status' => InstallmentPlanStatus::Open,
                'notes' => $this->nullableString($data['notes'] ?? null),
            ]);

            $base = Carbon::parse($firstDue)->startOfDay();
            $paidSet = array_fill_keys($paidNumbers, true);

            for ($n = 1; $n <= $total; $n++) {
                $due = $base->copy()->addMonthsNoOverflow($n - 1)->toDateString();
                $isPaid = isset($paidSet[$n]);

                InstallmentItem::query()->create([
                    'installment_plan_id' => $plan->id,
                    'number' => $n,
                    'amount' => $amount,
                    'due_on' => $due,
                    'status' => $isPaid ? InstallmentItemStatus::Paid : InstallmentItemStatus::Open,
                    'paid_at' => $isPaid ? now() : null,
                    'transaction_id' => null,
                    'loan_id' => null,
                ]);
            }

            $this->refreshPlanStatus($plan);

            if ($debtor !== null) {
                $this->syncLoansForPlan($user, $plan->fresh(['items', 'debtor']));
            }

            return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
        });
    }

    /**
     * @param  array{
     *     title?: string,
     *     notes?: string|null,
     *     debtor_id?: int|null,
     *     credit_card_id?: int|null
     * }  $data
     */
    public function update(User $user, InstallmentPlan $plan, array $data): InstallmentPlan
    {
        $this->assertOwner($user, $plan);

        if (array_key_exists('title', $data)) {
            $plan->title = trim((string) $data['title']);
        }

        if (array_key_exists('notes', $data)) {
            $plan->notes = $this->nullableString($data['notes']);
        }

        $debtorChanged = false;
        if (array_key_exists('debtor_id', $data)) {
            $newDebtorId = $data['debtor_id'] !== null ? (int) $data['debtor_id'] : null;
            if ($newDebtorId !== null) {
                $owns = Debtor::query()->forUser($user)->whereKey($newDebtorId)->exists();
                if (! $owns) {
                    throw ValidationException::withMessages([
                        'debtor_id' => ['Pessoa inválida ou não pertence a você.'],
                    ]);
                }
            }
            if ((int) ($plan->debtor_id ?? 0) !== (int) ($newDebtorId ?? 0)) {
                $plan->debtor_id = $newDebtorId;
                $debtorChanged = true;
            }
        }

        if (array_key_exists('credit_card_id', $data)) {
            $cardId = $data['credit_card_id'] !== null ? (int) $data['credit_card_id'] : null;
            if ($cardId !== null) {
                $owns = CreditCard::query()->whereKey($cardId)->where('user_id', $user->id)->exists();
                if (! $owns) {
                    throw ValidationException::withMessages([
                        'credit_card_id' => ['Cartão inválido ou não pertence a você.'],
                    ]);
                }
            }
            $plan->credit_card_id = $cardId;
        }

        $plan->save();

        if ($debtorChanged) {
            if ($plan->debtor_id === null) {
                $this->detachLoansFromPlan($plan->fresh(['items']));
            } else {
                $this->syncLoansForPlan($user, $plan->fresh(['items', 'debtor']));
            }
        }

        return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
    }

    public function markItemPaid(User $user, InstallmentPlan $plan, int $number): InstallmentPlan
    {
        $this->assertOwner($user, $plan);

        $item = $plan->items()->where('number', $number)->first();
        if ($item === null) {
            throw ValidationException::withMessages([
                'number' => ['Parcela não encontrada.'],
            ]);
        }

        if ($item->status === InstallmentItemStatus::Cancelled) {
            throw ValidationException::withMessages([
                'number' => ['Parcela cancelada.'],
            ]);
        }

        if ($item->status !== InstallmentItemStatus::Paid) {
            $item->status = InstallmentItemStatus::Paid;
            $item->paid_at = now();
            $item->save();
        }

        if ($item->loan_id !== null) {
            $loan = Loan::query()->forUser($user)->whereKey($item->loan_id)->first();
            if ($loan !== null && ! in_array($loan->status, [LoanStatus::Paid, LoanStatus::Cancelled], true)) {
                $loan->markPaid(null);
            }
        }

        $this->refreshPlanStatus($plan);

        return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
    }

    public function markItemOpen(User $user, InstallmentPlan $plan, int $number): InstallmentPlan
    {
        $this->assertOwner($user, $plan);

        $item = $plan->items()->where('number', $number)->first();
        if ($item === null) {
            throw ValidationException::withMessages([
                'number' => ['Parcela não encontrada.'],
            ]);
        }

        if ($item->status === InstallmentItemStatus::Cancelled) {
            throw ValidationException::withMessages([
                'number' => ['Parcela cancelada.'],
            ]);
        }

        $item->status = InstallmentItemStatus::Open;
        $item->paid_at = null;
        $item->save();

        if ($item->loan_id !== null) {
            $loan = Loan::query()->forUser($user)->whereKey($item->loan_id)->first();
            if ($loan !== null && $loan->status !== LoanStatus::Cancelled) {
                $loan->status = LoanStatus::Open;
                $loan->paid_amount = '0.00';
                $loan->paid_at = null;
                $loan->save();
            }
        } elseif ($plan->debtor_id !== null) {
            $this->syncLoansForPlan($user, $plan->fresh(['items', 'debtor']));
        }

        $this->refreshPlanStatus($plan);

        return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
    }

    public function cancel(User $user, InstallmentPlan $plan): InstallmentPlan
    {
        $this->assertOwner($user, $plan);

        DB::transaction(function () use ($user, $plan): void {
            $plan->load('items');

            foreach ($plan->items as $item) {
                if ($item->status === InstallmentItemStatus::Open) {
                    $item->status = InstallmentItemStatus::Cancelled;
                    $item->save();
                }

                if ($item->loan_id !== null) {
                    $loan = Loan::query()->forUser($user)->whereKey($item->loan_id)->first();
                    if ($loan !== null && ! in_array($loan->status, [LoanStatus::Paid, LoanStatus::Cancelled], true)) {
                        $loan->status = LoanStatus::Cancelled;
                        $loan->save();
                    }
                }
            }

            $plan->status = InstallmentPlanStatus::Cancelled;
            $plan->save();
        });

        return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
    }

    /**
     * Attach debtor to an existing plan (from link-transactions) and sync loans.
     */
    public function attachDebtor(User $user, InstallmentPlan $plan, Debtor $debtor): InstallmentPlan
    {
        $this->assertOwner($user, $plan);

        if ((int) $debtor->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'debtor' => ['Pessoa não pertence ao usuário autenticado.'],
            ]);
        }

        $plan->debtor_id = $debtor->id;
        $plan->save();

        $this->syncLoansForPlan($user, $plan->fresh(['items', 'debtor']));

        return $plan->fresh(['items', 'creditCard:id,name', 'debtor:id,name']);
    }

    /**
     * Ensure open items have loans when plan has a debtor; link tx.loan_id.
     */
    public function syncLoansForPlan(User $user, InstallmentPlan $plan): void
    {
        $this->assertOwner($user, $plan);

        if ($plan->debtor_id === null) {
            return;
        }

        $debtor = $plan->relationLoaded('debtor') && $plan->debtor !== null
            ? $plan->debtor
            : Debtor::query()->forUser($user)->whereKey($plan->debtor_id)->first();

        if ($debtor === null) {
            return;
        }

        $plan->loadMissing('items');

        foreach ($plan->items as $item) {
            if ($item->status === InstallmentItemStatus::Cancelled) {
                continue;
            }

            if ($item->status === InstallmentItemStatus::Paid) {
                if ($item->loan_id !== null) {
                    $loan = Loan::query()->forUser($user)->whereKey($item->loan_id)->first();
                    if ($loan !== null && $loan->status !== LoanStatus::Paid && $loan->status !== LoanStatus::Cancelled) {
                        $loan->markPaid(null);
                    }
                }

                continue;
            }

            // Open item — ensure loan.
            if ($item->loan_id !== null) {
                $loan = Loan::query()->forUser($user)->whereKey($item->loan_id)->first();
                if ($loan !== null) {
                    if ($loan->debtor_id !== $debtor->id) {
                        $loan->debtor_id = $debtor->id;
                        $loan->debtor_name = $debtor->name;
                        $loan->save();
                    }
                    if ($item->transaction_id !== null) {
                        Transaction::query()
                            ->forUser($user)
                            ->whereKey($item->transaction_id)
                            ->where(function ($q) use ($loan): void {
                                $q->whereNull('loan_id')->orWhere('loan_id', '!=', $loan->id);
                            })
                            ->update(['loan_id' => $loan->id]);
                    }

                    continue;
                }
            }

            $occurredOn = $item->due_on?->format('Y-m-d') ?? now()->toDateString();
            $description = $plan->title.' '.$item->number.'/'.$plan->total_count;

            if ($item->transaction_id !== null) {
                $tx = Transaction::query()->forUser($user)->whereKey($item->transaction_id)->first();
                if ($tx !== null) {
                    $occurredOn = $tx->occurred_on?->format('Y-m-d') ?? $occurredOn;
                    $description = is_string($tx->description) ? $tx->description : $description;

                    // Reuse existing loan on the transaction if it already belongs to this debtor.
                    if ($tx->loan_id !== null) {
                        $existingLoan = Loan::query()->forUser($user)->whereKey($tx->loan_id)->first();
                        if ($existingLoan !== null && (int) ($existingLoan->debtor_id ?? 0) === (int) $debtor->id) {
                            $item->loan_id = $existingLoan->id;
                            $item->save();

                            continue;
                        }
                    }
                }
            }

            $loan = $this->createLoanForItem($user, $debtor, $plan, $item, $occurredOn, $description);

            $item->loan_id = $loan->id;
            $item->save();

            if ($item->transaction_id !== null) {
                Transaction::query()
                    ->forUser($user)
                    ->whereKey($item->transaction_id)
                    ->update(['loan_id' => $loan->id]);
            }
        }
    }

    private function createLoanForItem(
        User $user,
        Debtor $debtor,
        InstallmentPlan $plan,
        InstallmentItem $item,
        string $occurredOn,
        string $description,
    ): Loan {
        $creditCardId = $plan->credit_card_id !== null ? (int) $plan->credit_card_id : null;
        $kind = $creditCardId !== null ? LoanKind::CardLimit : LoanKind::Cash;

        if ($kind === LoanKind::CardLimit) {
            $owns = CreditCard::query()
                ->whereKey($creditCardId)
                ->where('user_id', $user->id)
                ->exists();
            if (! $owns) {
                $creditCardId = null;
                $kind = LoanKind::Cash;
            }
        }

        return Loan::query()->create([
            'user_id' => $user->id,
            'debtor_id' => $debtor->id,
            'debtor_name' => $debtor->name,
            'credit_card_id' => $creditCardId,
            'kind' => $kind,
            'amount' => number_format((float) $item->amount, 2, '.', ''),
            'currency' => 'BRL',
            'lent_on' => $occurredOn,
            'due_on' => $occurredOn,
            'status' => LoanStatus::Open,
            'paid_amount' => '0.00',
            'paid_at' => null,
            'notes' => 'Parcela '.$item->number.'/'.$plan->total_count.' · '.$description,
        ]);
    }

    private function detachLoansFromPlan(InstallmentPlan $plan): void
    {
        $plan->loadMissing('items');

        foreach ($plan->items as $item) {
            if ($item->loan_id === null) {
                continue;
            }

            $loanId = (int) $item->loan_id;
            $item->loan_id = null;
            $item->save();

            // Leave the loan itself; just unlink from the installment series.
            // Optionally cancel open loans that were only for this series — keep them if tx still linked.
            $loan = Loan::query()->whereKey($loanId)->first();
            if ($loan !== null
                && in_array($loan->status, [LoanStatus::Open, LoanStatus::Partial], true)
                && ! Transaction::query()->where('loan_id', $loanId)->exists()
            ) {
                $loan->status = LoanStatus::Cancelled;
                $loan->save();
            }
        }
    }

    private function createItemsForNewPlan(
        InstallmentPlan $plan,
        int $current,
        int $total,
        string $amount,
        string $occurredOn,
        Transaction $tx,
    ): void {
        $base = Carbon::parse($occurredOn)->startOfDay();

        for ($n = 1; $n <= $total; $n++) {
            $offset = $n - $current;
            $due = $base->copy()->addMonthsNoOverflow($offset)->toDateString();

            if ($n < $current) {
                InstallmentItem::query()->create([
                    'installment_plan_id' => $plan->id,
                    'number' => $n,
                    'amount' => $amount,
                    'due_on' => $due,
                    'status' => InstallmentItemStatus::Paid,
                    'paid_at' => null,
                    'transaction_id' => null,
                    'loan_id' => null,
                ]);
            } elseif ($n === $current) {
                InstallmentItem::query()->create([
                    'installment_plan_id' => $plan->id,
                    'number' => $n,
                    'amount' => number_format(abs((float) $tx->amount), 2, '.', ''),
                    'due_on' => $occurredOn,
                    'status' => InstallmentItemStatus::Open,
                    'paid_at' => null,
                    'transaction_id' => $tx->id,
                    'loan_id' => null,
                ]);
            } else {
                InstallmentItem::query()->create([
                    'installment_plan_id' => $plan->id,
                    'number' => $n,
                    'amount' => $amount,
                    'due_on' => $due,
                    'status' => InstallmentItemStatus::Open,
                    'paid_at' => null,
                    'transaction_id' => null,
                    'loan_id' => null,
                ]);
            }
        }
    }

    private function applyTransactionToItem(
        InstallmentItem $item,
        Transaction $tx,
        bool $preservePaidStatus = false,
    ): void {
        $item->transaction_id = $tx->id;
        $item->amount = number_format(abs((float) $tx->amount), 2, '.', '');
        $item->due_on = $tx->occurred_on?->format('Y-m-d') ?? $item->due_on?->format('Y-m-d');

        if (! $preservePaidStatus || $item->status !== InstallmentItemStatus::Paid) {
            if ($item->status === InstallmentItemStatus::Cancelled) {
                // leave cancelled
            } elseif ($item->status !== InstallmentItemStatus::Paid) {
                $item->status = InstallmentItemStatus::Open;
                $item->paid_at = null;
            }
        }

        $item->save();
    }

    private function findMatchingPlan(
        User $user,
        string $title,
        int $total,
        string $amount,
        ?int $creditCardId,
    ): ?InstallmentPlan {
        $query = InstallmentPlan::query()
            ->forUser($user)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->where('total_count', $total)
            ->where('installment_amount', $amount)
            ->where('status', '!=', InstallmentPlanStatus::Cancelled->value)
            ->with('items');

        if ($creditCardId === null) {
            $query->whereNull('credit_card_id');
        } else {
            $query->where('credit_card_id', $creditCardId);
        }

        return $query->orderByDesc('id')->first();
    }

    public function refreshPlanStatus(InstallmentPlan $plan): void
    {
        $plan->load('items');

        if ($plan->status === InstallmentPlanStatus::Cancelled) {
            return;
        }

        $items = $plan->items->filter(
            fn (InstallmentItem $i) => $i->status !== InstallmentItemStatus::Cancelled,
        );

        if ($items->isEmpty()) {
            $plan->status = InstallmentPlanStatus::Cancelled;
            $plan->save();

            return;
        }

        $open = $items->filter(fn (InstallmentItem $i) => $i->status === InstallmentItemStatus::Open)->count();
        $paid = $items->filter(fn (InstallmentItem $i) => $i->status === InstallmentItemStatus::Paid)->count();

        if ($open === 0 && $paid > 0) {
            $plan->status = InstallmentPlanStatus::Paid;
        } elseif ($paid > 0 && $open > 0) {
            $plan->status = InstallmentPlanStatus::Partial;
        } else {
            $plan->status = InstallmentPlanStatus::Open;
        }

        $plan->save();
    }

    private function assertOwner(User $user, InstallmentPlan $plan): void
    {
        if ((int) $plan->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'plan' => ['Parcelamento não pertence ao usuário autenticado.'],
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
