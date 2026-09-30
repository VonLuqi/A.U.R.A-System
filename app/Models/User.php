<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'password',
        'role',
        'is_active',
        'uploads_used',
        'manual_transactions_used',
        'quota_period_starts_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'avatar_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'uploads_used' => 'integer',
            'manual_transactions_used' => 'integer',
            'quota_period_starts_at' => 'datetime',
        ];
    }

    /**
     * Public URL for the profile avatar on disk `public`, or null when unset.
     * Path stored in `avatar_path` is relative (e.g. avatars/{id}/{uuid}.webp).
     */
    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null || $this->avatar_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasRole(UserRole|string $role): bool
    {
        $value = $role instanceof UserRole ? $role : UserRole::from($role);

        return $this->role === $value;
    }

    public function hasAnyRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function canUpload(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return app(\App\Services\UsageLimitService::class)
            ->can($this, \App\Services\UsageLimitService::METRIC_UPLOADS);
    }

    /**
     * Remaining uploads in the current quota window; null = unlimited.
     */
    public function remainingUploads(): ?int
    {
        return app(\App\Services\UsageLimitService::class)
            ->remaining($this, \App\Services\UsageLimitService::METRIC_UPLOADS);
    }

    /**
     * @return HasMany<StatementImport, $this>
     */
    public function statementImports(): HasMany
    {
        return $this->hasMany(StatementImport::class);
    }

    /**
     * @return HasMany<Goal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * @return HasMany<TransactionAlias, $this>
     */
    public function transactionAliases(): HasMany
    {
        return $this->hasMany(TransactionAlias::class);
    }

    /**
     * @return HasMany<CreditCard, $this>
     */
    public function creditCards(): HasMany
    {
        return $this->hasMany(CreditCard::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Direct ownership (multi-tenant). Prefer this over the import HasManyThrough.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Legacy path via statement_imports (import-backed rows only).
     *
     * @return HasManyThrough<Transaction, StatementImport, $this>
     */
    public function transactionsViaImports(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, StatementImport::class);
    }
}
