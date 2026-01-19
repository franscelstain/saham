<?php

namespace App\Trade\Watchlist;

/**
 * SRP: menentukan mode pembelian (NO_TRADE/BUY_1/BUY_2_SPLIT) untuk pre-open.
 * Tidak menghitung lot karena modal user tidak diberikan di endpoint ini.
 */
class WatchlistAllocationEngine
{
    private const LOT_SIZE = 100; // IDX standard lot

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

    /**
     * Build "action plan" untuk meta.recommendations (WATCHLIST.md).
     * Output ini dipakai UI untuk menampilkan saran eksekusi harian tanpa redundant list.
     *
     * @param array<int,array> $topPicksRows (row sudah berupa output snake_case)
     * @param string $dow Mon/Tue/Wed/Thu/Fri
     * @param float|null $capitalTotal
     */
    public function buildActionPlan(array $topPicksRows, string $dow, ?float $capitalTotal = null): array
    {
        $base = [
            'mode' => 'NO_TRADE',
            'positions' => 0,
            'split' => [],
            'split_ratio' => '',
            'rationale' => ['Tidak ada pick yang layak dieksekusi hari ini.'],
            'entry_windows' => ['09:20-10:30', '13:35-14:30'],
            'avoid_windows' => ['09:00-09:15', '15:45-close'],
            'entry_rule' => 'LIMIT_ONLY',
            'entry_slices' => 2,
            'slice_plan' => ['60% di entry, 40% saat konfirmasi (hold/break).'],
            'capital_total' => $capitalTotal,
            'reserve_cash_pct' => 0.03,
            'buy_plan' => [],
        ];

        if (empty($topPicksRows)) {
            return $base;
        }

        // Default rule: Jumat NO_TRADE untuk menghindari risk weekend (kecuali nanti user override via UI)
        if ($dow === 'Fri') {
            $base['rationale'] = ['Hari Jumat: default NO_TRADE untuk menghindari risk weekend.'];
            return $base;
        }

        // Entry windows/rules: ambil dari pick teratas kalau ada
        $first = $topPicksRows[0];
        if (!empty($first['entry_windows']) && is_array($first['entry_windows'])) {
            $base['entry_windows'] = array_values($first['entry_windows']);
        }
        if (!empty($first['avoid_windows']) && is_array($first['avoid_windows'])) {
            $base['avoid_windows'] = array_values($first['avoid_windows']);
        }
        if (!empty($first['entry_style'])) {
            $base['entry_rule'] = (string)$first['entry_style'];
        }

        // Tentukan jumlah posisi (max 3) secara deterministik
        $n = count($topPicksRows);
        $p1 = $topPicksRows[0] ?? null;
        $p2 = $topPicksRows[1] ?? null;
        $p3 = $topPicksRows[2] ?? null;

        $s1 = (float)($p1['rank_score'] ?? $p1['watchlist_score'] ?? 0);
        $s2 = (float)($p2['rank_score'] ?? $p2['watchlist_score'] ?? 0);
        $s3 = (float)($p3['rank_score'] ?? $p3['watchlist_score'] ?? 0);

        $positions = 1;
        if ($n >= 2) {
            $positions = 2;
        }
        if ($n >= 3) {
            // BUY_3_SPLIT hanya kalau pick #3 masih cukup dekat & tidak terlalu lemah
            $gap23 = $s2 - $s3;
            if ($s3 >= 45 && $gap23 <= 8) {
                $positions = 3;
            }
        }

        // Split ratio
        $split = [];
        if ($positions === 1) {
            $base['mode'] = 'BUY_1';
            $base['positions'] = 1;
            $split = [
                ['ticker' => (string)($p1['ticker'] ?? ''), 'alloc_pct' => 1.0],
            ];
            $base['split_ratio'] = '100';
            $base['rationale'] = ['Fokus 1 posisi terbaik untuk eksekusi yang lebih disiplin.'];
        } elseif ($positions === 2) {
            $base['mode'] = 'BUY_2_SPLIT';
            $base['positions'] = 2;

            $gap = $s1 - $s2;
            $a = 0.6; $b = 0.4;
            if ($gap >= 12) { $a = 0.8; $b = 0.2; }
            elseif ($gap >= 6) { $a = 0.7; $b = 0.3; }

            $split = [
                ['ticker' => (string)($p1['ticker'] ?? ''), 'alloc_pct' => (float)$a],
                ['ticker' => (string)($p2['ticker'] ?? ''), 'alloc_pct' => (float)$b],
            ];
            $base['split_ratio'] = (int)round($a*100) . ':' . (int)round($b*100);
            $base['rationale'] = [
                'Bagi modal ke 2 posisi untuk diversifikasi risk.',
                'Prioritas lebih besar ke pick dengan skor lebih tinggi.',
            ];
        } else {
            $base['mode'] = 'BUY_3_SPLIT';
            $base['positions'] = 3;

            // default 50/30/20, tapi kalau gap besar -> 60/25/15
            $a = 0.5; $b = 0.3; $c = 0.2;
            if (($s1 - $s2) >= 10) { $a = 0.6; $b = 0.25; $c = 0.15; }

            $split = [
                ['ticker' => (string)($p1['ticker'] ?? ''), 'alloc_pct' => (float)$a],
                ['ticker' => (string)($p2['ticker'] ?? ''), 'alloc_pct' => (float)$b],
                ['ticker' => (string)($p3['ticker'] ?? ''), 'alloc_pct' => (float)$c],
            ];
            $base['split_ratio'] = (int)round($a*100) . ':' . (int)round($b*100) . ':' . (int)round($c*100);
            $base['rationale'] = [
                '3 posisi hanya saat semua top picks cukup layak & skor berdekatan.',
                'Tetap utamakan pick #1 untuk probabilitas lebih tinggi.',
            ];
        }

        $base['split'] = $split;

        // Build buy_plan jika capital diberikan
        if ($capitalTotal !== null && $capitalTotal > 0 && !empty($split)) {
            $reservePct = (float)$base['reserve_cash_pct'];
            if ($reservePct < 0) $reservePct = 0.0;
            if ($reservePct > 0.10) $reservePct = 0.10; // hard cap biar gak aneh

            $usable = $capitalTotal * (1.0 - $reservePct);
            $remaining = $capitalTotal;

            $plan = [];
            foreach ($split as $s) {
                $ticker = (string)($s['ticker'] ?? '');
                $pct = (float)($s['alloc_pct'] ?? 0);
                $budget = $usable * $pct;

                $row = $this->findRowByTicker($topPicksRows, $ticker);
                $entry = null;
                if ($row) {
                    $tp = $row['trade_plan'] ?? null;
                    if (is_array($tp) && isset($tp['entry']) && is_numeric($tp['entry'])) {
                        $entry = (float)$tp['entry'];
                    }
                    if ($entry === null && isset($row['close']) && is_numeric($row['close'])) {
                        $entry = (float)$row['close'];
                    }
                }

                $lots = null;
                $est = null;
                if ($entry !== null && $entry > 0) {
                    $lotCost = $entry * self::LOT_SIZE;
                    $lots = (int) floor($budget / $lotCost);
                    if ($lots < 0) $lots = 0;
                    $est = $lots * $lotCost;
                    $remaining -= $est;
                }

                $plan[] = [
                    'ticker' => $ticker,
                    'alloc_pct' => $pct,
                    'budget' => $budget,
                    'entry_price' => $entry,
                    'lot_size' => self::LOT_SIZE,
                    'lots' => $lots,
                    'estimated_cost' => $est,
                    'remaining_cash' => $remaining,
                ];
            }

            $base['buy_plan'] = $plan;
        } elseif (!empty($split)) {
            // template buy_plan tanpa modal
            $plan = [];
            foreach ($split as $s) {
                $ticker = (string)($s['ticker'] ?? '');
                $row = $this->findRowByTicker($topPicksRows, $ticker);
                $entry = null;
                if ($row) {
                    $tp = $row['trade_plan'] ?? null;
                    if (is_array($tp) && isset($tp['entry']) && is_numeric($tp['entry'])) {
                        $entry = (float)$tp['entry'];
                    }
                    if ($entry === null && isset($row['close']) && is_numeric($row['close'])) {
                        $entry = (float)$row['close'];
                    }
                }

                $plan[] = [
                    'ticker' => $ticker,
                    'alloc_pct' => (float)($s['alloc_pct'] ?? 0),
                    'budget' => null,
                    'entry_price' => $entry,
                    'lot_size' => self::LOT_SIZE,
                    'lots' => null,
                    'estimated_cost' => null,
                    'remaining_cash' => null,
                ];
            }
            $base['buy_plan'] = $plan;
        }

        return $base;
    }

    private function findRowByTicker(array $rows, string $ticker): ?array
    {
        $ticker = strtoupper(trim($ticker));
        foreach ($rows as $r) {
            $t = strtoupper((string)($r['ticker'] ?? ''));
            if ($t !== '' && $t === $ticker) return $r;
        }
        return null;
    }
}
