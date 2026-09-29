<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class StatementImport extends Model
{
    /** @use HasFactory<\Database\Factories\StatementImportFactory> */
    use HasFactory;

    /** Etapa C §4.5 — status machine (MVP skips pending → starts at processing). */
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const FORMAT_CSV = 'csv';

    public const FORMAT_OFX = 'ofx';

    public const FORMAT_CSV_CREDIT_CARD = 'csv_credit_card';

    public const SOURCE_NUBANK = 'nubank';

    public const SOURCE_NUBANK_CREDIT = 'nubank_credit';

    public const SOURCE_OTHER = 'other';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /** @var list<string> */
    public const FORMATS = [
        self::FORMAT_CSV,
        self::FORMAT_OFX,
        self::FORMAT_CSV_CREDIT_CARD,
    ];

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_NUBANK,
        self::SOURCE_NUBANK_CREDIT,
        self::SOURCE_OTHER,
    ];

    /**
     * Allowed transitions (MVP: create as processing; no auto-reprocess).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING],
        self::STATUS_PROCESSING => [self::STATUS_COMPLETED, self::STATUS_FAILED],
        self::STATUS_COMPLETED => [],
        self::STATUS_FAILED => [],
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'original_filename',
        'stored_path',
        'format',
        'source',
        'status',
        'rows_total',
        'rows_imported',
        'rows_skipped',
        'checksum',
        'error_message',
        'purged_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows_total' => 'integer',
            'rows_imported' => 'integer',
            'rows_skipped' => 'integer',
            'purged_at' => 'datetime',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function canTransitionTo(string $to): bool
    {
        $from = (string) $this->status;

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function transitionTo(string $to, array $attributes = []): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidArgumentException(
                "Invalid statement_imports status transition: {$this->status} → {$to}."
            );
        }

        $this->fill(array_merge($attributes, ['status' => $to]))->save();
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
}
