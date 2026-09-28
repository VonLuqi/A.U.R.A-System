<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Fatal statement parse failure (Etapa C §3.2.2 / §4.4 / §5.3).
 * Controllers / exception handler map this to HTTP 422. Never include absolute filesystem paths.
 */
class InvalidStatementException extends Exception
{
    public ?int $importId = null;

    public function __construct(
        string $message = 'Não foi possível ler o extrato.',
        public readonly ?string $errorCode = 'invalid_statement',
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->sanitizeMessage($message), 0, $previous);
    }

    public function withImportId(int $importId): static
    {
        $this->importId = $importId;

        return $this;
    }

    private function sanitizeMessage(string $message): string
    {
        // Strip Windows/Unix absolute paths that might leak from IO exceptions.
        $message = preg_replace('#[A-Za-z]:\\\\[^\s]+#', '[path]', $message) ?? $message;
        $message = preg_replace('#/(?:home[^/\s]*|var|tmp|Users)/[^\s]+#', '[path]', $message) ?? $message;
        $message = preg_replace('#\\\\[^\s]+\\\\[^\s]+#', '[path]', $message) ?? $message;

        return trim($message) !== '' ? trim($message) : 'Não foi possível ler o extrato.';
    }
}
