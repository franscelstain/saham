<?php

namespace App\Services\Watchlist;

class WatchlistGrouper
{
    private function applyTopPicksLimit(array $groups): array
    {
        $maxTop = (int) config('trade.watchlist.top_picks_max', 5);
        if ($maxTop <= 0) return $groups;

        $top = $groups['top_picks'] ?? [];
        if (count($top) <= $maxTop) return $groups;

        // overflow: top_picks yang kepotong
        $overflow = array_slice($top, $maxTop);

        // keep only maxTop
        $groups['top_picks'] = array_slice($top, 0, $maxTop);

        // overflow pindah ke WATCH, ditaruh paling atas (tetap urutan rank tinggi)
        $watch = $groups['watch'] ?? [];
        $groups['watch'] = array_merge($overflow, $watch);

        return $groups;
    }

    public function group(array $rows): array
    {
        $groups = $this->groupOnly($rows);

        // LIMIT top_picks, overflow pindah ke WATCH (tetap urutan teratas)
        $groups = $this->applyTopPicksLimit($groups);

        $meta   = $this->stats($rows, $groups);

        return [
            'groups' => $groups,
            'meta'   => $meta,
        ];
    }

    public function groupOnly(array $rows): array
    {
        $out = [
            'top_picks' => [],
            'watch'     => [],
            'avoid'     => [],
        ];

        foreach ($rows as $r) {
            $bucket = $r['bucket'] ?? null;

            // fleksibel: bucket bisa string atau array
            if (is_array($bucket)) {
                $bucket = $bucket['name'] ?? $bucket['bucket'] ?? null;
            }

            if ($bucket === 'TOP_PICKS') {
                $out['top_picks'][] = $r;
            } elseif ($bucket === 'WATCH') {
                $out['watch'][] = $r;
            } else {
                $out['avoid'][] = $r;
            }
        }

        return $out;
    }

    public function stats(array $rows, array $groups): array
    {
        $expired = 0;
        $planInvalid = 0;
        $rrBelowMin = 0;

        foreach ($rows as $r) {
            if (!empty($r['isExpired'])) $expired++;

            $errors = $r['plan']['errors'] ?? [];
            if (!empty($errors)) $planInvalid++;

            $codes = $r['rankReasonCodes'] ?? [];
            if (!empty($codes) && in_array('RR_BELOW_MIN', $codes, true)) {
                $rrBelowMin++;
            }
        }

        return [
            'counts' => [
                'top_picks' => count($groups['top_picks'] ?? []),
                'watch'     => count($groups['watch'] ?? []),
                'avoid'     => count($groups['avoid'] ?? []),
                'total'     => count($rows),
            ],
            'quality' => [
                'expired'      => $expired,
                'plan_invalid' => $planInvalid,
                'rr_below_min' => $rrBelowMin,
            ],
        ];
    }
}
