<?php

declare(strict_types=1);

namespace Tests\Unit\AntiDrift;

use PHPUnit\Framework\TestCase;

final class TestDtoImmutabilityAntiDriftTest extends TestCase
{
    public function test_unit_tests_must_not_mutate_candidate_input_properties(): void
    {
        // DTO.md: DTO should be treated as immutable; tests must not encourage mutation.
        $testsRoot = dirname(__DIR__, 2); // tests/
        $paths = $this->collectPhpFiles($testsRoot . '/Unit');

        $forbidden = [
            // Common watchlist fields historically mutated in tests
            '->decisionCode',
            '->signalCode',
            '->volumeLabelCode',
            '->dv20',
            '->dv20Idr',
            '->ma20',
            '->ma50',
            '->hh20',
            '->ll5',
        ];

        $hits = [];
        foreach ($paths as $path) {
            $content = file_get_contents($path);
            if (!is_string($content)) {
                continue;
            }

            foreach ($forbidden as $needle) {
                // Only flag as mutation when it's an assignment.
                if (preg_match('/' . preg_quote($needle, '/') . '\s*=/', $content)) {
                    $hits[] = [$path, $needle];
                }
            }
        }

        $this->assertSame([], $hits, 'Found DTO mutations in unit tests. Convert to constructor-based input instead.');
    }

    /** @return string[] */
    private function collectPhpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }
}
