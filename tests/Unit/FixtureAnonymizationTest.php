<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * §7.2 — fixtures must stay anonymized (never commit real extracts / PII).
 */
class FixtureAnonymizationTest extends TestCase
{
    /** @var list<string> */
    private const PII_PATTERNS = [
        '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', // e-mail
        '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', // CPF formatado
        '/\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/', // CNPJ formatado
        '/\b(?:João|Maria|Silva|Souza|Oliveira)\b/iu',
    ];

    public function test_statement_fixtures_contain_no_obvious_pii(): void
    {
        $root = base_path('tests/Fixtures/statements');
        $this->assertDirectoryExists($root);

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) === 'md') {
                continue;
            }
            $files[] = $file->getPathname();
        }

        $this->assertNotEmpty($files, 'Expected CSV/OFX fixtures under tests/Fixtures/statements');

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents, "Unable to read [{$path}]");

            foreach (self::PII_PATTERNS as $pattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $contents,
                    'PII-like pattern found in fixture: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $path)
                );
            }
        }
    }

    public function test_realistic_anon_fixture_uses_placeholders_not_real_names(): void
    {
        $path = base_path('tests/Fixtures/statements/nubank/sample_account_realistic_anon.csv');
        $this->assertFileExists($path);

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('[PESSOA A]', $contents);
        $this->assertStringContainsString('[PESSOA B]', $contents);
        $this->assertStringContainsString('[PIX_KEY]', $contents);
        $this->assertStringContainsString('[EMPRESA]', $contents);
        $this->assertStringNotContainsString('@gmail.com', $contents);
    }
}
