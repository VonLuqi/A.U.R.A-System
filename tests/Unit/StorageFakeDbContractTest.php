<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * §7.10 — Feature tests use RefreshDatabase; statement I/O uses Storage::fake + Fixtures only.
 */
class StorageFakeDbContractTest extends TestCase
{
    /** Feature tests that hit DB factories / Eloquent — must use RefreshDatabase. */
    private const REQUIRES_REFRESH_DATABASE = [
        'Analytics',
        'Auth',
        'Categories',
        'Services',
        'Statements',
        'Storage/StorageAcceptanceTest.php',
        'Transactions',
        'ApiRouteInventoryTest.php',
        'HttpErrorContractTest.php',
        'RoutesWiringTest.php',
        'TransactionsAnalyticsAcceptanceTest.php',
    ];

    /** Feature tests that write to the statements disk — must Storage::fake. */
    private const REQUIRES_STORAGE_FAKE = [
        'Statements/UploadStatementEndpointTest.php',
        'Statements/UploadStatementValidationTest.php',
        'Statements/StatementUploadServiceTest.php',
        'Statements/StatementRetentionTest.php',
        'Storage/StorageAcceptanceTest.php',
        'Storage/StatementsDiskTest.php',
        'Auth/RateLimitTest.php',
        'HttpErrorContractTest.php',
    ];

    public function test_feature_db_tests_use_refresh_database(): void
    {
        foreach ($this->featurePhpFiles() as $relative => $contents) {
            if (! $this->matchesAny($relative, self::REQUIRES_REFRESH_DATABASE)) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/use\s+RefreshDatabase/',
                $contents,
                "Missing RefreshDatabase in Feature test [{$relative}]"
            );
        }
    }

    public function test_feature_statement_io_tests_call_storage_fake(): void
    {
        foreach ($this->featurePhpFiles() as $relative => $contents) {
            if (! $this->matchesAny($relative, self::REQUIRES_STORAGE_FAKE)) {
                continue;
            }

            $this->assertStringContainsString(
                'Storage::fake',
                $contents,
                "Missing Storage::fake in statement I/O Feature test [{$relative}]"
            );
        }
    }

    public function test_feature_tests_do_not_reference_absolute_user_paths_as_fixtures(): void
    {
        foreach ($this->featurePhpFiles() as $relative => $contents) {
            // Allow asserting that absolute paths are NOT present in responses.
            $stripped = preg_replace(
                '/assertStringNotContainsString\([^;]+;/',
                '',
                $contents
            ) ?? $contents;

            $this->assertDoesNotMatchRegularExpression(
                '/[\'"][A-Za-z]:\\\\Users\\\\/',
                $stripped,
                "Feature test [{$relative}] must not hardcode user absolute paths as fixtures"
            );
        }
    }

    public function test_statement_fixtures_live_under_tests_fixtures(): void
    {
        $this->assertDirectoryExists(base_path('tests/Fixtures/statements'));
        $this->assertFileExists(base_path('tests/Fixtures/statements/nubank/sample_account.csv'));
        $this->assertFileExists(base_path('tests/Fixtures/statements/ofx/sample_nubank.ofx'));
    }

    /**
     * @return array<string, string> relative path => contents
     */
    private function featurePhpFiles(): array
    {
        $root = base_path('tests/Feature');
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    /**
     * @param  list<string>  $needles  directory prefix (`Auth/`) or exact relative file path
     */
    private function matchesAny(string $relative, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($relative === $needle) {
                return true;
            }

            // Directory prefix: "Auth" or "Auth/" matches Auth/**
            $dir = rtrim($needle, '/');
            if (! str_contains($dir, '.') && str_starts_with($relative, $dir.'/')) {
                return true;
            }
        }

        return false;
    }
}
