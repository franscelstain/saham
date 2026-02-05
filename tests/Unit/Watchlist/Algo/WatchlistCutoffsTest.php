<?php

namespace Tests\Unit\Watchlist\Algo;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Algo\WatchlistAlgoConfig;
use App\Trade\Watchlist\Algo\WatchlistCutoffs;

final class WatchlistCutoffsTest extends TestCase
{
    public function testTopPickCutoffUsesMaxFormula(): void
    {
        // When S0 is high, use (S0 - gap)
        $s0 = 0.90;
        $cut = WatchlistCutoffs::topPickCutoff($s0);
        $this->assertSame($s0 - WatchlistAlgoConfig::TOPPICK_SCORE_GAP, $cut);

        // When S0 is low, clamp to MIN
        $s0 = 0.60;
        $cut = WatchlistCutoffs::topPickCutoff($s0);
        $this->assertSame(WatchlistAlgoConfig::TOPPICK_MIN_SCORE, $cut);
    }

    public function testRecommendationsCutoffUsesMaxFormula(): void
    {
        $s0 = 0.88;
        $cut = WatchlistCutoffs::recommendationsCutoff($s0);
        $this->assertSame($s0 - WatchlistAlgoConfig::RECO_SCORE_GAP, $cut);

        $s0 = 0.65;
        $cut = WatchlistCutoffs::recommendationsCutoff($s0);
        $this->assertSame(WatchlistAlgoConfig::MIN_RECO_SCORE, $cut);
    }
}
