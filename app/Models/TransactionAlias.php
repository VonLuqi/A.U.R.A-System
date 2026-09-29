<?php

namespace App\Models;

use App\Enums\AliasMatchType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAlias extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionAliasFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'match_type',
        'match_pattern',
        'display_name',
        'category_id',
        'priority',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_type' => AliasMatchType::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
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
     * @param  Builder<TransactionAlias>  $query
     * @return Builder<TransactionAlias>
     */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return $query->where('transaction_aliases.user_id', $userId);
    }

    /**
     * @param  Builder<TransactionAlias>  $query
     * @return Builder<TransactionAlias>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Active rules for a user, ordered by priority ASC then id ASC.
     *
     * @param  Builder<TransactionAlias>  $query
     * @return Builder<TransactionAlias>
     */
    public function scopeForResolution(Builder $query, int|User $user): Builder
    {
        return $query
            ->forUser($user)
            ->active()
            ->orderBy('priority')
            ->orderBy('id');
    }

    /**
     * Whether this alias matches a raw statement description.
     */
    public function matches(string $rawDescription): bool
    {
        $haystack = $rawDescription;
        $needle = $this->match_pattern;

        return match ($this->match_type) {
            AliasMatchType::Exact => strcasecmp($haystack, $needle) === 0,
            AliasMatchType::Contains => mb_stripos($haystack, $needle) !== false,
            AliasMatchType::StartsWith => mb_stripos($haystack, $needle) === 0,
            AliasMatchType::Regex => $this->matchesRegex($haystack, $needle),
        };
    }

    private function matchesRegex(string $haystack, string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        $delimited = str_starts_with($pattern, '/')
            ? $pattern
            : '/'.$pattern.'/iu';

        set_error_handler(static fn () => true);
        try {
            $result = preg_match($delimited, $haystack);
        } finally {
            restore_error_handler();
        }

        return $result === 1;
    }
}
