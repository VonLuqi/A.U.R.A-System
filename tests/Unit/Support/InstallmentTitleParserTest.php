<?php

namespace Tests\Unit\Support;

use App\Support\InstallmentTitleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstallmentTitleParserTest extends TestCase
{
    #[DataProvider('titles')]
    public function test_parses_installment_titles(
        string $input,
        ?array $expected,
    ): void {
        $this->assertSame($expected, InstallmentTitleParser::parse($input));
    }

    /**
     * @return array<string, array{0: string, 1: array{title: string, current: int, total: int}|null}>
     */
    public static function titles(): array
    {
        return [
            'magazine' => [
                'Magazine Luiza 1/12',
                ['title' => 'Magazine Luiza', 'current' => 1, 'total' => 12],
            ],
            'parcela word' => [
                'Shopee *X – Parcela 4/10',
                ['title' => 'Shopee *X', 'current' => 4, 'total' => 10],
            ],
            'parcela plain dash' => [
                'Ec *Pichauinforma - Parcela 5/12',
                ['title' => 'Ec *Pichauinforma', 'current' => 5, 'total' => 12],
            ],
            'no match' => [
                'Uber *Trip',
                null,
            ],
            'invalid current' => [
                'Foo 13/12',
                null,
            ],
        ];
    }
}
