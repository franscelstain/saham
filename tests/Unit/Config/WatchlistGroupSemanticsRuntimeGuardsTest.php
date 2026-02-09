<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class WatchlistGroupSemanticsRuntimeGuardsTest extends TestCase
{
    public function testGroupSemanticsRuntimeThresholdsAreSane(): void
    {
        $gs = config('trade.watchlist.group_semantics');
        $this->assertIsArray($gs, "config('trade.watchlist.group_semantics') must be array");

        foreach (['option', 'toppick_min_score', 'toppick_score_gap', 'secondary_min_score', 'watch_only_min_score', 'toppick_max', 'secondary_max', 'watch_only_max'] as $k) {
            $this->assertArrayHasKey($k, $gs, "config('trade.watchlist.group_semantics') missing: {$k}");
        }

        $this->assertIsString($gs['option']);

        $this->assertFraction($gs['toppick_min_score'], 'toppick_min_score');
        $this->assertFraction($gs['toppick_score_gap'], 'toppick_score_gap');
        $this->assertFraction($gs['secondary_min_score'], 'secondary_min_score');
        $this->assertFraction($gs['watch_only_min_score'], 'watch_only_min_score');

        $toppickMin = (float) $gs['toppick_min_score'];
        $gap = (float) $gs['toppick_score_gap'];
        $secondaryMin = (float) $gs['secondary_min_score'];
        $watchOnlyMin = (float) $gs['watch_only_min_score'];

        // ordering must be monotonic so semantics don't overlap weirdly
        // PHPUnit: assertGreaterThanOrEqual($expected, $actual) -> asserts $actual >= $expected
        $this->assertGreaterThanOrEqual($secondaryMin, $toppickMin, 'toppick_min_score must be >= secondary_min_score');
        $this->assertGreaterThanOrEqual($watchOnlyMin, $secondaryMin, 'secondary_min_score must be >= watch_only_min_score');

        // compute a plausible top_cut and ensure it stays within [0..1]
        $s0 = 1.0;
        $topCut = max($toppickMin, $s0 - $gap);
        $this->assertGreaterThanOrEqual(0.0, $topCut, 'top_cut must be >= 0');
        $this->assertLessThanOrEqual(1.0, $topCut, 'top_cut must be <= 1');

        foreach (['toppick_max', 'secondary_max', 'watch_only_max'] as $k) {
            $this->assertIsInt($gs[$k], "{$k} must be int");
            $this->assertGreaterThanOrEqual(0, $gs[$k], "{$k} must be >= 0");
        }
    }

    private function assertFraction($v, string $name): void
    {
        $this->assertIsNumeric($v, "{$name} must be numeric");
        $f = (float) $v;
        $this->assertGreaterThanOrEqual(0.0, $f, "{$name} must be >= 0.0");
        $this->assertLessThanOrEqual(1.0, $f, "{$name} must be <= 1.0");
    }
}
