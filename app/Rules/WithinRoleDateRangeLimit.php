<?php

namespace App\Rules;

use App\Models\RoleLimit;
use App\Models\User;
use App\Services\UsageLimitService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Caps inclusive from/to span by role max_date_range_days (PLAN_EXPANSAO §2.4).
 *
 * `0` = unlimited. Attach to the `to` field (reads `from` from sibling data).
 */
class WithinRoleDateRangeLimit implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly ?User $user,
        private readonly string $fromKey = 'from',
        private readonly string $toKey = 'to',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->user === null) {
            return;
        }

        $from = $this->data[$this->fromKey] ?? null;
        $to = $this->data[$this->toKey] ?? $value;

        if (! is_string($from) || $from === '' || ! is_string($to) || $to === '') {
            return;
        }

        /** @var UsageLimitService $limits */
        $limits = app(UsageLimitService::class);
        $max = $limits->dateRangeDaysLimit($this->user);

        if (RoleLimit::isUnlimited($max)) {
            return;
        }

        try {
            $days = $limits->inclusiveDaySpan($from, $to);
        } catch (\Throwable) {
            return;
        }

        if ($days > $max) {
            $fail("O intervalo máximo permitido para o seu perfil é de {$max} dias (solicitado: {$days}).");
        }
    }
}
