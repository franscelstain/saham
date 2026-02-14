<?php

namespace Tests\Unit\Docs;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Policies\PolicyFactory;

class WatchlistPolicyCoverageTest extends TestCase
{
    private function stripNumberPrefix(string $name): string
    {
        return (string) preg_replace('/^\d+\./', '', $name);
    }

    public function testDocsFolderAndKnownPoliciesAreInSync(): void
    {
        $dir = base_path('docs/watchlist/policy');
        $this->assertDirectoryExists($dir);

        $docNames = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.md') as $path) {
            $base = basename((string)$path);
            $docNames[] = $this->stripNumberPrefix($base);
        }
        $docNames = array_values(array_unique($docNames));
        sort($docNames);

        $expectedDocs = [];
        foreach (PolicyFactory::knownPolicies() as $policy) {
            $expectedDocs[] = strtolower($policy) . '.md';
        }
        sort($expectedDocs);

        $this->assertSame(
            $expectedDocs,
            $docNames,
            'Policy docs folder must contain exactly the docs for known watchlist policies (numbered prefixes allowed).'
        );
    }
}
