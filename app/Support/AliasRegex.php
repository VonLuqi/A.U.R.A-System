<?php

namespace App\Support;

/**
 * Validate alias regex patterns safely (PLAN_EXPANSAO §4.2).
 */
final class AliasRegex
{
    public static function isValid(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        $delimited = str_starts_with($pattern, '/')
            ? $pattern
            : '/'.$pattern.'/iu';

        set_error_handler(static fn () => true);
        try {
            $result = preg_match($delimited, '');
        } finally {
            restore_error_handler();
        }

        return $result !== false;
    }
}
