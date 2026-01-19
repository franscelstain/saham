<?php

namespace App\Services\Watchlist;

/**
 * SRP: sorting logic untuk hasil watchlist.
 */
class WatchlistSorter
{
    /**
     * Sort by:
     * 1) rankScore desc
     * 2) valueEst desc
     * 3) rrTp2 desc
     */
    public function sort(array &$rows): void
    {
        usort($rows, function ($a, $b) {
            $s = ($b['rankScore'] ?? 0) <=> ($a['rankScore'] ?? 0);
            if ($s !== 0) return $s;

            $l = ($b['valueEst'] ?? 0) <=> ($a['valueEst'] ?? 0);
            if ($l !== 0) return $l;

            $r = (($b['plan']['rrTp2'] ?? 0) <=> ($a['plan']['rrTp2'] ?? 0));
            if ($r !== 0) return $r;

            // deterministic tie-breaker
            return strcmp((string)($a['code'] ?? ''), (string)($b['code'] ?? ''));
        });
    }
}
