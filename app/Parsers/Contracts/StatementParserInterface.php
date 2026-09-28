<?php

namespace App\Parsers\Contracts;

use App\DTOs\ParseResult;
use SplFileInfo;

/**
 * Contract for statement file parsers (CSV / OFX).
 *
 * format: csv | ofx
 * source: MVP = nubank (extensible later)
 */
interface StatementParserInterface
{
    public function supports(string $format, string $source): bool;

    /**
     * @param  SplFileInfo|string  $file  Absolute path or SplFileInfo pointing at the statement file.
     */
    public function parse(SplFileInfo|string $file): ParseResult;
}
