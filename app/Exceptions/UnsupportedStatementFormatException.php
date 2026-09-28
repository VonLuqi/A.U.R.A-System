<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * No registered parser supports the given format/source (Etapa C §3.6 / §4.4).
 * Controllers / exception handler map this to HTTP 422.
 */
class UnsupportedStatementFormatException extends Exception
{
    public function __construct(
        string $message = 'Formato de extrato não suportado.',
        public readonly ?string $errorCode = 'unsupported_format',
        public readonly ?string $format = null,
        public readonly ?string $source = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : 'Formato de extrato não suportado.', 0, $previous);
    }
}
