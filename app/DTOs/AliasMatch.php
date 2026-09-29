<?php

namespace App\DTOs;

/**
 * Result of resolving a statement description against user aliases (PLAN_EXPANSAO §4.1).
 */
final readonly class AliasMatch
{
    public function __construct(
        public int $aliasId,
        public string $displayName,
        public ?int $categoryId,
    ) {}
}
