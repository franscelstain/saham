<?php

namespace App\Trade\Watchlist\Algo;

/**
 * AllocationComparator
 *
 * Deterministic ordering for allocation per docs/watchlist/watchlist.md.
 *
 * Order:
 * 1) score_total desc
 * 2) plan.rr_est desc
 * 3) plan.stop_pct asc
 * 4) dv20_idr desc
 * 5) atr_pct asc
 * 6) ticker_code asc
 */
final class AllocationComparator
{
    public static function compare(array $a, array $b): int
    {
        // score_total desc
        $c = self::cmpDesc($a['score_total'] ?? null, $b['score_total'] ?? null);
        if ($c !== 0) return $c;

        // rr desc (nulls last)
        $c = self::cmpDesc($a['ticker_plan']['rr_est'] ?? null, $b['ticker_plan']['rr_est'] ?? null);
        if ($c !== 0) return $c;

        // stop_pct asc (nulls last)
        $c = self::cmpAsc($a['ticker_plan']['stop_pct'] ?? null, $b['ticker_plan']['stop_pct'] ?? null);
        if ($c !== 0) return $c;

        // dv20_idr desc
        $c = self::cmpDesc($a['dv20_idr'] ?? null, $b['dv20_idr'] ?? null);
        if ($c !== 0) return $c;

        // atr_pct asc
        $c = self::cmpAsc($a['atr_pct'] ?? null, $b['atr_pct'] ?? null);
        if ($c !== 0) return $c;

        // ticker asc
        return strcmp((string)($a['ticker'] ?? ''), (string)($b['ticker'] ?? ''));
    }

    private static function cmpDesc($x, $y): int
    {
        if ($x === null && $y === null) return 0;
        if ($x === null) return 1;
        if ($y === null) return -1;
        if ($x == $y) return 0;
        return ($x > $y) ? -1 : 1;
    }

    private static function cmpAsc($x, $y): int
    {
        if ($x === null && $y === null) return 0;
        if ($x === null) return 1;
        if ($y === null) return -1;
        if ($x == $y) return 0;
        return ($x < $y) ? -1 : 1;
    }

    private function __construct()
    {
        // static only
    }
}
