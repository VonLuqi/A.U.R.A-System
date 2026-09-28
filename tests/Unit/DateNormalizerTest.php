<?php

namespace Tests\Unit;

use App\Support\DateNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateNormalizerTest extends TestCase
{
    #[DataProvider('brazilianProvider')]
    public function test_from_brazilian(string $raw, string $expected): void
    {
        $this->assertSame($expected, DateNormalizer::fromBrazilian($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function brazilianProvider(): array
    {
        return [
            'padded' => ['27/09/2026', '2026-09-27'],
            'unpadded' => ['1/9/2026', '2026-09-01'],
            'nbsp trailing' => ["27/09/2026\xC2\xA0", '2026-09-27'],
            'nbsp surrounding' => ["\xC2\xA027/09/2026\xC2\xA0", '2026-09-27'],
            'spaces' => ['  05/01/2026  ', '2026-01-05'],
        ];
    }

    #[DataProvider('ofxProvider')]
    public function test_from_ofx(string $raw, string $expected): void
    {
        $this->assertSame($expected, DateNormalizer::fromOfx($raw));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function ofxProvider(): array
    {
        return [
            'date only' => ['20260927', '2026-09-27'],
            'datetime' => ['20260927123045', '2026-09-27'],
            'datetime ms tz' => ['20260927123045.000[-3:GMT]', '2026-09-27'],
        ];
    }

    public function test_invalid_brazilian_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateNormalizer::fromBrazilian('2026-09-27');
    }

    public function test_invalid_calendar_brazilian_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateNormalizer::fromBrazilian('32/13/2026');
    }

    public function test_invalid_ofx_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateNormalizer::fromOfx('27/09/2026');
    }
}
