<?php

namespace App\Models;

use App\Enums\InstallmentPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Série de parcelas (cartão e/ou cobrança a pessoa).
 */
class InstallmentPlan extends Model
{
    /** @use HasFactory<\Database\Factories\InstallmentPlanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'credit_card_id',
        'debtor_id',
        'title',
        'total_count',
        'installment_amount',
        'currency',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'installment_amount' => 'decimal:2',
            'status' => InstallmentPlanStatus::class,
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
     * @return BelongsTo<CreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /**
     * @return BelongsTo<Debtor, $this>
     */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Debtor::class);
    }

    /**
     * @return HasMany<InstallmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InstallmentItem::class)->orderBy('number');
    }

    /**
     * @param  Builder<InstallmentPlan>  $query
     * @return Builder<InstallmentPlan>
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $query->where('installment_plans.user_id', $userId);
    }

    public function paidCount(): int
    {
        if ($this->relationLoaded('items')) {
            return $this->items
                ->filter(fn (InstallmentItem $i) => $i->status === \App\Enums\InstallmentItemStatus::Paid)
                ->count();
        }

        return $this->items()
            ->where('status', \App\Enums\InstallmentItemStatus::Paid)
            ->count();
    }

    public function openRemainingTotal(): float
    {
        if ($this->relationLoaded('items')) {
            return round(
                $this->items
                    ->filter(fn (InstallmentItem $i) => $i->status === \App\Enums\InstallmentItemStatus::Open)
                    ->sum(fn (InstallmentItem $i) => (float) $i->amount),
                2,
            );
        }

        return (float) $this->items()
            ->where('status', \App\Enums\InstallmentItemStatus::Open)
            ->sum('amount');
    }
}
