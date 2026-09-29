<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Usage quota exhausted (PLAN_EXPANSAO §2.1 / §2.4).
 * Rendered as HTTP 429 JSON by bootstrap/app.php.
 */
class UsageLimitExceededException extends Exception
{
    public function __construct(
        public readonly string $metric,
        public readonly int $limit,
        public readonly int $used,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : 'Limite de uso excedido para este recurso.',
            $code,
            $previous,
        );
    }
}
