<?php

namespace App\Parsers;

use App\Exceptions\UnsupportedStatementFormatException;
use App\Parsers\Contracts\StatementParserInterface;

/**
 * Selects a StatementParserInterface by format + source (Etapa C §3.6).
 */
final class StatementParserResolver
{
    /**
     * @param  list<StatementParserInterface>  $parsers
     */
    public function __construct(
        private readonly array $parsers,
    ) {
        foreach ($this->parsers as $index => $parser) {
            if (! $parser instanceof StatementParserInterface) {
                throw new \InvalidArgumentException(
                    "parsers[{$index}] must implement StatementParserInterface."
                );
            }
        }
    }

    public function resolve(string $format, string $source): StatementParserInterface
    {
        $format = strtolower(trim($format));
        $source = strtolower(trim($source));

        foreach ($this->parsers as $parser) {
            if ($parser->supports($format, $source)) {
                return $parser;
            }
        }

        throw new UnsupportedStatementFormatException(
            message: "Formato de extrato não suportado: {$format}/{$source}.",
            format: $format,
            source: $source,
        );
    }
}
