<?php

namespace Tests\Unit\Docs;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Policies\PolicyFactory;

class WatchlistPolicyDocsExistTest extends TestCase
{
    public function testPolicyDocFilesExistForAllKnownPolicies(): void
    {
        $base = base_path('docs/watchlist/policy');
        $this->assertDirectoryExists($base);

        foreach (PolicyFactory::knownPolicies() as $policy) {
            $file = strtolower($policy) . '.md';
            $plain = $base . DIRECTORY_SEPARATOR . $file;

            // Allow numbered prefix variants: "1.weekly_swing.md"
            $glob = glob($base . DIRECTORY_SEPARATOR . '*.' . $file);
            $exists = file_exists($plain) || (!empty($glob));

            $this->assertTrue($exists, "Missing policy doc for {$policy}: expected {$plain} (or numbered variant).");
        }
    }
}
