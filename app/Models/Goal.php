<?php

namespace App\Models;

use App\Enums\GoalKind;
use App\Enums\GoalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    /** @use HasFactory<\Database\Factories\GoalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'kind',
        'target_amount',
        'current_amount',
        'currency',
        'deadline_on',
        'category_id',
        'linked_description_pattern',
        'status',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => GoalKind::class,
            'status' => GoalStatus::class,
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'deadline_on' => 'date',
            'metadata' => 'array',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<Goal>  $query
     * @return Builder<Goal>
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $query->where('goals.user_id', $userId);
    }

    /**
     * @param  Builder<Goal>  $query
     * @return Builder<Goal>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GoalStatus::Active);
    }

    /**
     * Progress ratio 0–100 (capped). Target ≤ 0 yields 0.
     */
    public function progressPercent(): float
    {
        $target = (float) $this->target_amount;
        if ($target <= 0) {
            return 0.0;
        }

        $ratio = ((float) $this->current_amount / $target) * 100;

        return round(min(100, max(0, $ratio)), 2);
    }

    public function remainingAmount(): string
    {
        $remaining = max(0, (float) $this->target_amount - (float) $this->current_amount);

        return number_format($remaining, 2, '.', '');
    }

    public function isLinked(): bool
    {
        return $this->category_id !== null || filled($this->linked_description_pattern);
    }
}
