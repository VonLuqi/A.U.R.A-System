<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * §8 — no debug dumps left in app/ (tests may mention dd in assertions).
 */
class NoDebugDumpTest extends TestCase
{
    public function test_app_has_no_dd_or_dump_calls(): void
    {
        $root = base_path('app');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            // Strip single/double-quoted strings so messages like "Found dd()" do not false-positive.
            $code = preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/s', "''", $contents) ?? $contents;
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w\\\\])dd\s*\(/',
                $code,
                "Found dd() in [{$relative}]"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w\\\\])dump\s*\(/',
                $code,
                "Found dump() in [{$relative}]"
            );
        }
    }
}
