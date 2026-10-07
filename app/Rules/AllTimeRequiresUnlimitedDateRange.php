<?php

namespace App\Rules;

use App\Models\RoleLimit;
use App\Models\User;
use App\Services\UsageLimitService;
use App\Support\DateRangeQuery;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * preset=all is only allowed when the role has unlimited max_date_range_days.
 */
class AllTimeRequiresUnlimitedDateRange implements ValidationRule
{
    public function __construct(
        private readonly ?User $user,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== DateRangeQuery::PRESET_ALL) {
            return;
        }

        if ($this->user === null) {
            return;
        }

        /** @var UsageLimitService $limits */
        $limits = app(UsageLimitService::class);
        $max = $limits->dateRangeDaysLimit($this->user);

        if (! RoleLimit::isUnlimited($max)) {
            $fail('Todo o histórico não está disponível para o seu perfil.');
        }
    }
}
