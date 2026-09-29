<?php

namespace App\Models;

use App\Enums\TransactionSourceKind;
use App\Support\TransactionHasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'statement_import_id',
        'source_kind',
        'category_id',
        'external_id',
        'occurred_on',
        'description',
        'amount',
        'type',
        'unique_hash',
        'raw_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
            'raw_payload' => 'array',
            'source_kind' => TransactionSourceKind::class,
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
     * @return BelongsTo<StatementImport, $this>
     */
    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(StatementImport::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $query->where('transactions.user_id', $userId);
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('type', 'credit');
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('type', 'debit');
    }

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeBetweenDates(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        return $query->whereBetween('occurred_on', [$from, $to]);
    }

    public static function makeUniqueHash(
        string $occurredOn,
        string|float|int $amount,
        string $type,
        string $description,
        ?string $externalId = null,
        string $source = 'nubank',
    ): string {
        return TransactionHasher::make(
            $occurredOn,
            $amount,
            $type,
            $description,
            $externalId,
            $source,
        );
    }
}
