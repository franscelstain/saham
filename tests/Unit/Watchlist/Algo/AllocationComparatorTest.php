<?php

namespace Tests\Unit\Watchlist\Algo;

use PHPUnit\Framework\TestCase;
use App\Trade\Watchlist\Algo\AllocationComparator;

final class AllocationComparatorTest extends TestCase
{
    public function testComparatorHonorsLockedOrder(): void
    {
        $a = [
            'ticker' => 'AAA',
            'score_total' => 0.80,
            'dv20_idr' => 10,
            'atr_pct' => 0.05,
            'ticker_plan' => ['rr_est' => 1.6, 'stop_pct' => 0.04],
        ];
        $b = [
            'ticker' => 'BBB',
            'score_total' => 0.80,
            'dv20_idr' => 12,
            'atr_pct' => 0.06,
            'ticker_plan' => ['rr_est' => 1.4, 'stop_pct' => 0.03],
        ];

        // Same score: rr desc wins => a should come first.
        $this->assertSame(-1, AllocationComparator::compare($a, $b));

        // If rr equal, stop_pct asc wins.
        $b['ticker_plan']['rr_est'] = 1.6;
        $this->assertSame(1, AllocationComparator::compare($a, $b)); // b has smaller stop_pct

        // If rr and stop equal, dv20 desc wins.
        $b['ticker_plan']['stop_pct'] = 0.04;
        $this->assertSame(1, AllocationComparator::compare($a, $b)); // b dv20 higher

        // If all equal, atr asc wins.
        $b['dv20_idr'] = 10;
        $b['atr_pct'] = 0.04;
        $this->assertSame(1, AllocationComparator::compare($a, $b)); // b atr smaller

        // If all equal, ticker asc wins.
        $b['atr_pct'] = 0.05;
        $b['ticker'] = 'AAB';
        $this->assertSame(-1, AllocationComparator::compare($a, $b));
    }
}
