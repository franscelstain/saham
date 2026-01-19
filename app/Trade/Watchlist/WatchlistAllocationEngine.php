<?php

namespace App\Trade\Watchlist;

/**
 * SRP: menentukan mode pembelian (NO_TRADE/BUY_1/BUY_2_SPLIT) untuk pre-open.
 * Tidak menghitung lot karena modal user tidak diberikan di endpoint ini.
 */
class WatchlistAllocationEngine
{
    /**
     * @param array<int,array> $recommendedRows (sudah sorted)
     * @param string $dow
     * @return array{mode:string,allocation:array<int,array{code:string,alloc_pct:float}>,notes:string}
     */
    public function decide(array $recommendedRows, string $dow): array
    {
        if (empty($recommendedRows)) {
            return [
                'mode' => 'NO_TRADE',
                'allocation' => [],
                'notes' => 'Tidak ada recommended pick yang memenuhi syarat hari ini.',
            ];
        }

        // default: Jumat -> NO_TRADE kecuali user override via UI nanti
        if ($dow === 'Fri') {
            return [
                'mode' => 'NO_TRADE',
                'allocation' => [],
                'notes' => 'Hari Jumat: default NO_TRADE untuk menghindari risk weekend.',
            ];
        }

        if (count($recommendedRows) === 1) {
            return [
                'mode' => 'BUY_1',
                'allocation' => [
                    ['code' => (string)($recommendedRows[0]['code'] ?? ''), 'alloc_pct' => 1.0],
                ],
                'notes' => 'Fokus 1 posisi terbaik.',
            ];
        }

        // Two picks: split by score gap (deterministik)
        $a = (float) ($recommendedRows[0]['watchlist_score'] ?? $recommendedRows[0]['rankScore'] ?? 0);
        $b = (float) ($recommendedRows[1]['watchlist_score'] ?? $recommendedRows[1]['rankScore'] ?? 0);
        $gap = $a - $b;

        $alloc1 = 0.6;
        $alloc2 = 0.4;
        if ($gap >= 12) {
            $alloc1 = 0.8;
            $alloc2 = 0.2;
        } elseif ($gap >= 6) {
            $alloc1 = 0.7;
            $alloc2 = 0.3;
        }

        return [
            'mode' => 'BUY_2_SPLIT',
            'allocation' => [
                ['code' => (string)($recommendedRows[0]['code'] ?? ''), 'alloc_pct' => (float) $alloc1],
                ['code' => (string)($recommendedRows[1]['code'] ?? ''), 'alloc_pct' => (float) $alloc2],
            ],
            'notes' => 'Bagi modal ke 2 posisi untuk diversifikasi risk.',
        ];
    }
}
