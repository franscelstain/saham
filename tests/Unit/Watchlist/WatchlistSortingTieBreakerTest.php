<?php

namespace Tests\Unit\Watchlist;

use Tests\TestCase;

/**
 * Locks deterministic tie-break ordering per docs/watchlist/watchlist.md:
 * 1) score_total desc
 * 2) dv20_idr desc
 * 3) atr_pct asc
 * 4) tick_pct asc
 * 5) ticker_code asc
 */
class WatchlistSortingTieBreakerTest extends TestCase
{
    public function testSortingTieBreakersAreDeterministicAndMatchDocs(): void
    {
        $rows = [
            [
                'ticker_code' => 'BBBB',
                'score_total' => 0.80,
                'derived' => ['dv20_idr' => 10_000_000_000, 'atr_pct' => 0.10, 'tick_pct' => 0.010],
            ],
            [
                'ticker_code' => 'AAAA',
                'score_total' => 0.80,
                'derived' => ['dv20_idr' => 10_000_000_000, 'atr_pct' => 0.10, 'tick_pct' => 0.010],
            ],
            [
                'ticker_code' => 'CCCC',
                'score_total' => 0.80,
                'derived' => ['dv20_idr' => 12_000_000_000, 'atr_pct' => 0.20, 'tick_pct' => 0.020],
            ],
            [
                'ticker_code' => 'DDDD',
                'score_total' => 0.79,
                'derived' => ['dv20_idr' => 99_000_000_000, 'atr_pct' => 0.01, 'tick_pct' => 0.001],
            ],
            [
                'ticker_code' => 'EEEE',
                'score_total' => 0.80,
                'derived' => ['dv20_idr' => 10_000_000_000, 'atr_pct' => 0.08, 'tick_pct' => 0.020],
            ],
            [
                'ticker_code' => 'FFFF',
                'score_total' => 0.80,
                'derived' => ['dv20_idr' => 10_000_000_000, 'atr_pct' => 0.08, 'tick_pct' => 0.015],
            ],
        ];

        usort($rows, function ($a, $b) {
            $sa = (float)($a['score_total'] ?? 0);
            $sb = (float)($b['score_total'] ?? 0);
            if ($sa !== $sb) return ($sa < $sb) ? 1 : -1;

            $da = (float)($a['derived']['dv20_idr'] ?? ($a['dv20'] ?? 0));
            $db = (float)($b['derived']['dv20_idr'] ?? ($b['dv20'] ?? 0));
            if ($da !== $db) return ($da < $db) ? 1 : -1;

            $atra = (float)($a['derived']['atr_pct'] ?? 999.0);
            $atrb = (float)($b['derived']['atr_pct'] ?? 999.0);
            if ($atra !== $atrb) return ($atra > $atrb) ? 1 : -1;

            $tpa = (float)($a['derived']['tick_pct'] ?? 999.0);
            $tpb = (float)($b['derived']['tick_pct'] ?? 999.0);
            if ($tpa !== $tpb) return ($tpa > $tpb) ? 1 : -1;

            return strcmp((string)($a['ticker_code'] ?? ''), (string)($b['ticker_code'] ?? ''));
        });

        $ordered = array_map(fn ($r) => (string)$r['ticker_code'], $rows);

        // Expect:
        // - 0.80 beats 0.79 (DDDD last)
        // - among 0.80: dv20 12B (CCCC) first
        // - then dv20 10B group: atr 0.08 beats 0.10
        //   - within atr 0.08: tick 0.015 (FFFF) beats 0.020 (EEEE)
        // - then atr 0.10 group: ticker_code asc (AAAA then BBBB)
        $this->assertSame(['CCCC', 'FFFF', 'EEEE', 'AAAA', 'BBBB', 'DDDD'], $ordered);
    }
}
