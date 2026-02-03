<?php

namespace Tests\Unit\Watchlist;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Support\WatchlistScoreScale;

class WatchlistScoreScaleTest extends TestCase
{
    public function test_scale_from_score_total_0_to_10(): void
    {
        $this->assertSame(0.0, WatchlistScoreScale::toWatchlistScore(null));
        $this->assertSame(0.0, WatchlistScoreScale::toWatchlistScore('nope'));

        $this->assertSame(70.0, WatchlistScoreScale::toWatchlistScore(7));
        $this->assertSame(92.5, WatchlistScoreScale::toWatchlistScore(9.25));
        $this->assertSame(100.0, WatchlistScoreScale::toWatchlistScore(10));
    }
}
