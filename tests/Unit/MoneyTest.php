<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('brazilianProvider')]
    public function test_parse_brazilian(string $raw, string $amount, string $type): void
    {
        $this->assertSame($amount, Money::parseBrazilian($raw));

        $signed = Money::parseBrazilianSigned($raw);
        $this->assertSame($amount, $signed['amount']);
        $this->assertSame($type, $signed['type']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function brazilianProvider(): array
    {
        return [
            'dot thousands' => ['1.234,56', '1234.56', 'credit'],
            'negative' => ['-1.234,56', '1234.56', 'debit'],
            'plain comma' => ['1234,56', '1234.56', 'credit'],
            'currency symbol' => ['R$ 10,00', '10.00', 'credit'],
            'currency no space' => ['R$10,00', '10.00', 'credit'],
            'brl prefix' => ['BRL 99,90', '99.90', 'credit'],
            'nbsp currency' => ["R$\xC2\xA01.000,50", '1000.50', 'credit'],
            'nbsp around value' => ["\xC2\xA0-25,00\xC2\xA0", '25.00', 'debit'],
            'parens debit' => ['(50,00)', '50.00', 'debit'],
            'trailing minus' => ['12,34-', '12.34', 'debit'],
            'leading plus' => ['+7,50', '7.50', 'credit'],
            'zero' => ['0,00', '0.00', 'credit'],
        ];
    }

    #[DataProvider('ofxProvider')]
    public function test_parse_ofx(string|float $raw, string $amount, string $type): void
    {
        $this->assertSame($amount, Money::parseOfx($raw));

        $signed = Money::parseOfxSigned($raw);
        $this->assertSame($amount, $signed['amount']);
        $this->assertSame($type, $signed['type']);
    }

    /**
     * @return array<string, array{0: string|float, 1: string, 2: string}>
     */
    public static function ofxProvider(): array
    {
        return [
            'positive string' => ['1234.56', '1234.56', 'credit'],
            'negative string' => ['-89.90', '89.90', 'debit'],
            'integer string' => ['100', '100.00', 'credit'],
            'comma thousands ofx-ish' => ['1,234.56', '1234.56', 'credit'],
            'float negative' => [-10.5, '10.50', 'debit'],
            'float positive' => [3.1, '3.10', 'credit'],
            'int zero' => [0, '0.00', 'credit'],
        ];
    }

    public function test_assert_non_negative_rejects_signed_or_malformed(): void
    {
        Money::assertNonNegative('0.00');

        $this->expectException(InvalidArgumentException::class);
        Money::assertNonNegative('-1.00');
    }

    public function test_invalid_brazilian_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseBrazilian('abc');
    }
}
