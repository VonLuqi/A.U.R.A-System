<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Etapa H §2.2 — cartão de crédito cadastrado (multi-tenant).
 */
class CreditCard extends Model
{
    /** @use HasFactory<\Database\Factories\CreditCardFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'limit_amount',
        'currency',
        'closing_day',
        'due_day',
        'last_four',
        'is_active',
        'is_default',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'limit_amount' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @param  Builder<CreditCard>  $query
     * @return Builder<CreditCard>
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $query->where('credit_cards.user_id', $userId);
    }

    /**
     * @param  Builder<CreditCard>  $query
     * @return Builder<CreditCard>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<CreditCard>  $query
     * @return Builder<CreditCard>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Próximo dia de vencimento (inclusive se `$from` cair no próprio due_day).
     */
    public function nextDueDate(?Carbon $from = null): Carbon
    {
        return $this->nextOccurrenceOfDay((int) $this->due_day, $from);
    }

    /**
     * Próximo dia de fechamento (inclusive se `$from` cair no próprio closing_day).
     */
    public function nextClosingDate(?Carbon $from = null): Carbon
    {
        return $this->nextOccurrenceOfDay((int) $this->closing_day, $from);
    }

    /**
     * Resolve day-of-month in `$from`'s month, clamped to daysInMonth;
     * if that date is before `$from`, advances one month.
     */
    private function nextOccurrenceOfDay(int $day, ?Carbon $from = null): Carbon
    {
        $tz = (string) config('app.timezone', 'America/Sao_Paulo');
        $from = ($from ?? Carbon::now($tz))->copy()->timezone($tz)->startOfDay();
        $day = max(1, min(31, $day));

        $candidate = $this->dateOnMonth($from->year, $from->month, $day, $tz);

        if ($candidate->lt($from)) {
            $nextMonth = $from->copy()->addMonthNoOverflow()->startOfMonth();

            return $this->dateOnMonth($nextMonth->year, $nextMonth->month, $day, $tz);
        }

        return $candidate;
    }

    private function dateOnMonth(int $year, int $month, int $day, string $tz): Carbon
    {
        $base = Carbon::create($year, $month, 1, 0, 0, 0, $tz);
        $clamped = min($day, $base->daysInMonth);

        return $base->day($clamped)->startOfDay();
    }
}
