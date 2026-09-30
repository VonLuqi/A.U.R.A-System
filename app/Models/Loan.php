<?php

namespace App\Models;

use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Etapa H §2.2 — empréstimo / cobrança a terceiro (multi-tenant).
 */
class Loan extends Model
{
    /** @use HasFactory<\Database\Factories\LoanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'debtor_id',
        'credit_card_id',
        'debtor_name',
        'kind',
        'amount',
        'currency',
        'lent_on',
        'due_on',
        'status',
        'paid_amount',
        'paid_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LoanKind::class,
            'status' => LoanStatus::class,
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'lent_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Debtor, $this>
     */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    /**
     * @return BelongsTo<CreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<Loan>  $query
     * @return Builder<Loan>
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $query->where('loans.user_id', $userId);
    }

    /**
     * @param  Builder<Loan>  $query
     * @return Builder<Loan>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', LoanStatus::Open);
    }

    /**
     * @param  Builder<Loan>  $query
     * @return Builder<Loan>
     */
    public function scopeDueBetween(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        return $query->whereBetween('due_on', [$from, $to]);
    }

    public function remainingAmount(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    public function isOverdue(): bool
    {
        if (! in_array($this->status, [LoanStatus::Open, LoanStatus::Partial], true)) {
            return false;
        }

        if ($this->due_on === null) {
            return false;
        }

        $tz = (string) config('app.timezone', 'America/Sao_Paulo');

        return $this->due_on->copy()->timezone($tz)->startOfDay()
            ->lt(Carbon::now($tz)->startOfDay());
    }

    /**
     * Registra pagamento. Sem `$amount`, quita o restante.
     * Atualiza status para `partial` ou `paid`.
     */
    public function markPaid(?float $amount = null): self
    {
        $remaining = $this->remainingAmount();
        $pay = $amount === null ? $remaining : round(max(0, $amount), 2);

        if ($pay <= 0 && $remaining > 0) {
            return $this;
        }

        $newPaid = round(min((float) $this->amount, (float) $this->paid_amount + $pay), 2);
        $this->paid_amount = number_format($newPaid, 2, '.', '');

        if ($newPaid >= (float) $this->amount - 0.001) {
            $this->paid_amount = number_format((float) $this->amount, 2, '.', '');
            $this->status = LoanStatus::Paid;
            $this->paid_at = now();
        } elseif ($newPaid > 0) {
            $this->status = LoanStatus::Partial;
            $this->paid_at = null;
        }

        $this->save();

        return $this;
    }
}
