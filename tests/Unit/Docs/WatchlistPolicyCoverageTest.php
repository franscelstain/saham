<?php

namespace Tests\Unit\Docs;

use App\Trade\Watchlist\WatchlistPolicyCodes;
use Tests\TestCase;

final class WatchlistPolicyCoverageTest extends TestCase
{
    public function testDocsFolderAndKnownPoliciesAreInSync(): void
    {
        $base = base_path('docs/watchlist/policy');
        $this->assertDirectoryExists($base);

        // Known policies (code)
        $known = WatchlistPolicyCodes::all();
        sort($known);

        // Docs present in folder
        $files = glob($base . DIRECTORY_SEPARATOR . '*.md') ?: [];
        $docNames = array_map(function ($p) {
            return basename((string)$p);
        }, $files);
        sort($docNames);

        // Expected docs (filename) derived from policy code naming.
        $expectedDocs = [];
        foreach ($known as $code) {
            $expectedDocs[] = strtolower($code) . '.md';
        }
        // But docs use short names (weekly_swing.md) not weekly_swing? Actually lower(WEEKLY_SWING)=weekly_swing.
        sort($expectedDocs);

        $this->assertSame($expectedDocs, $docNames, 'Policy docs folder must contain exactly the docs for known watchlist policies (no missing, no extra).');
    }
}
