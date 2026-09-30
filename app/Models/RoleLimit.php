<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;

class RoleLimit extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'max_uploads',
        'max_manual_transactions',
        'max_date_range_days',
        'max_goals',
        'max_credit_cards',
        'max_loans',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'max_uploads' => 'integer',
            'max_manual_transactions' => 'integer',
            'max_date_range_days' => 'integer',
            'max_goals' => 'integer',
            'max_credit_cards' => 'integer',
            'max_loans' => 'integer',
        ];
    }

    /**
     * Whether the given numeric limit is treated as unlimited (`0`).
     */
    public static function isUnlimited(int $limit): bool
    {
        return $limit === 0;
    }
}
