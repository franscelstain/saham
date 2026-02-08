<?php

namespace Tests\Unit\Docs;

use Tests\TestCase;

final class WatchlistDocParityTest extends TestCase
{
    public function testGroupSemanticsThresholdDefaultsMatchDocs(): void
    {
        $docPath = base_path('docs/watchlist/watchlist.md');
        $this->assertFileExists($docPath);

        $md = (string) file_get_contents($docPath);

        // Hard guardrail against unit confusion: docs must clearly state score_total is a fraction in [0..1].
        $this->assertMatchesRegularExpression('/score_total\b.*\[0\.\.1\]/i', $md);

        // Ensure the numeric grouping section exists (naming may evolve, semantics must not).
        $this->assertMatchesRegularExpression('/group(ing)?\s+semantics/i', $md);

        // Ensure the top_cut formula is present (allow minor formatting/backticks).
        $this->assertMatchesRegularExpression('/top_cut\s*=\s*max\(\s*TOPPICK_MIN_SCORE\s*,\s*S0\s*-\s*TOPPICK_SCORE_GAP\s*\)/', $md);

        $expected = [
            'toppick_min_score' => $this->extractFloat($md, 'TOPPICK_MIN_SCORE'),
            'toppick_score_gap' => $this->extractFloat($md, 'TOPPICK_SCORE_GAP'),
            'secondary_min_score' => $this->extractFloat($md, 'SECONDARY_MIN_SCORE'),
            'watch_only_min_score' => $this->extractFloat($md, 'WATCH_ONLY_MIN_SCORE'),
        ];

        // Parity is about documented DEFAULTS vs code defaults.
        // Do not let user/local env overrides (e.g. WATCHLIST_TOPPICK_MIN_SCORE=0.75)
        // change the resolved config values for this test.
        $this->unsetEnv([
            'WATCHLIST_TOPPICK_MIN_SCORE',
            'WATCHLIST_TOPPICK_SCORE_GAP',
            'WATCHLIST_SECONDARY_MIN_SCORE',
            'WATCHLIST_WATCH_ONLY_MIN_SCORE',
        ]);

        // IMPORTANT: Do not read via config() because Laravel may have already loaded
        // config values with env overrides before this test runs.
        $cfgAll = require base_path('config/trade.php');
        $this->assertIsArray($cfgAll);

        $cfg = (array)($cfgAll['watchlist']['group_semantics'] ?? []);
        $this->assertNotEmpty($cfg, 'config/trade.php must define watchlist.group_semantics');

        foreach ($expected as $key => $v) {
            $this->assertArrayHasKey($key, $cfg, "config('trade.watchlist.group_semantics') missing: {$key}");

            $actual = (float) $cfg[$key];

            // units: must be fraction 0..1
            $this->assertGreaterThanOrEqual(0.0, $actual, "{$key} must be >= 0");
            $this->assertLessThanOrEqual(1.0, $actual, "{$key} must be <= 1");

            $this->assertEqualsWithDelta($v, $actual, 1e-9, "{$key} must match docs/watchlist/watchlist.md");
        }
    }

    private function extractFloat(string $md, string $name): float
    {
        $re = '/\b' . preg_quote($name, '/') . '\s*=\s*([0-9]+(?:\.[0-9]+)?)/';
        if (!preg_match($re, $md, $m)) {
            $this->fail("Unable to find {$name} in docs/watchlist/watchlist.md");
        }
        return (float) $m[1];
    }

    /** @param array<int,string> $keys */
    private function unsetEnv(array $keys): void
    {
        foreach ($keys as $k) {
            // unset from process env
            @putenv($k);
            // unset from superglobals used by dotenv
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }
}
