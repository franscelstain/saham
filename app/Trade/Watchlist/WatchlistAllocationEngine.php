<?php

namespace App\Trade\Watchlist;

/**
 * SRP: menentukan mode pembelian (NO_TRADE/BUY_1/BUY_2_SPLIT) untuk pre-open.
 * Tidak menghitung lot karena modal user tidak diberikan di endpoint ini.
 */
class WatchlistAllocationEngine
{
    private const LOT_SIZE = 100; // IDX standard lot

    // Default entry split (deterministic; tune later if needed)
    private const SLICE_PCTS_2 = [0.6, 0.4];
    private const SLICE_PCTS_3 = [0.5, 0.3, 0.2];

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
        // NOTE:
        // Output meta.recommendations sekarang mendukung banyak strategi (ranked).
        // Field lama (mode/positions/split/buy_plan) tetap ada untuk kompatibilitas,
        // dan merepresentasikan strategi #1 (paling ideal).
        $base = [
            // selected (best) strategy fields (backward compatible)
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

            // new: ranked strategies (best first)
            'selected_strategy_id' => null,
            'strategies' => [],
        ];

        if (empty($topPicksRows)) {
            return $base;
        }

        // Default rule: Jumat NO_TRADE untuk menghindari risk weekend (kecuali nanti user override via UI)
        if ($dow === 'Fri') {
            $base['rationale'] = ['Hari Jumat: default NO_TRADE untuk menghindari risk weekend.'];
            $base['strategies'] = [
                [
                    'strategy_id' => 'S0',
                    'label' => 'NO_TRADE',
                    'mode' => 'NO_TRADE',
                    'positions' => 0,
                    'split' => [],
                    'split_ratio' => '',
                    'rank_score' => 0.0,
                    'why' => ['Hari Jumat: default NO_TRADE untuk menghindari risk weekend.'],
                    'risk_flags' => ['WEEKEND_RISK'],
                    'buy_plan' => [],
                ]
            ];
            $base['selected_strategy_id'] = 'S0';
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

        // Build ranked strategies (BUY_3, BUY_2, BUY_1) based on top_picks.
        $strategies = $this->buildStrategies($topPicksRows, $dow, $capitalTotal, (array)$base['entry_windows'], (array)$base['avoid_windows'], (string)$base['entry_rule'], (float)$base['reserve_cash_pct']);

        if (empty($strategies)) {
            // fall back: still offer BUY_1 template if exists
            $base['rationale'] = ['Tidak ada strategi yang feasible berdasarkan aturan saat ini.'];
            return $base;
        }

        $base['strategies'] = $strategies;
        $base['selected_strategy_id'] = (string)($strategies[0]['strategy_id'] ?? null);

        // Backward-compatible fields = strategy #1
        $best = $strategies[0];
        $base['mode'] = (string)($best['mode'] ?? $base['mode']);
        $base['positions'] = (int)($best['positions'] ?? $base['positions']);
        $base['split'] = (array)($best['split'] ?? []);
        $base['split_ratio'] = (string)($best['split_ratio'] ?? '');
        $base['rationale'] = (array)($best['why'] ?? $base['rationale']);
        $base['buy_plan'] = (array)($best['buy_plan'] ?? []);

        return $base;
    }

    /**
     * Build multiple ranked strategies from top picks.
     *
     * @param array<int,array> $topPicksRows (snake_case)
     * @return array<int,array>
     */
    private function buildStrategies(array $topPicksRows, string $dow, ?float $capitalTotal, array $entryWindows, array $avoidWindows, string $entryRule, float $reservePct): array
    {
        $max = 5;
        $rows = array_values(array_slice($topPicksRows, 0, $max));
        if (empty($rows)) return [];

        // Ensure reserve_pct sane
        if ($reservePct < 0) $reservePct = 0.0;
        if ($reservePct > 0.10) $reservePct = 0.10;

        // Build candidate strategies in a deterministic order.
        $candidates = [];

        // BUY_3_SPLIT candidate (uses top 3)
        if (count($rows) >= 3) {
            $s = $this->makeStrategyBuy3($rows, $dow, $capitalTotal, $entryWindows, $avoidWindows, $entryRule, $reservePct);
            if ($s !== null) $candidates[] = $s;
        }

        // BUY_2_SPLIT candidate (uses top 2)
        if (count($rows) >= 2) {
            $s = $this->makeStrategyBuy2($rows, $dow, $capitalTotal, $entryWindows, $avoidWindows, $entryRule, $reservePct);
            if ($s !== null) $candidates[] = $s;
        }

        // BUY_1_ONLY candidate
        $s = $this->makeStrategyBuy1($rows, $dow, $capitalTotal, $entryWindows, $avoidWindows, $entryRule, $reservePct);
        if ($s !== null) $candidates[] = $s;

        if (empty($candidates)) return [];

        // Rank strategies (best first)
        usort($candidates, function ($a, $b) {
            $sa = (float)($a['rank_score'] ?? 0);
            $sb = (float)($b['rank_score'] ?? 0);
            if ($sa === $sb) {
                $pa = (int)($a['positions'] ?? 0);
                $pb = (int)($b['positions'] ?? 0);
                // prefer simpler execution if equal score
                if ($pa === $pb) return strcmp((string)($a['strategy_id'] ?? ''), (string)($b['strategy_id'] ?? ''));
                return $pa <=> $pb;
            }
            return ($sa > $sb) ? -1 : 1;
        });

        return array_values($candidates);
    }

    private function makeStrategyBuy1(array $rows, string $dow, ?float $capitalTotal, array $entryWindows, array $avoidWindows, string $entryRule, float $reservePct): ?array
    {
        $p1 = $rows[0] ?? null;
        if (!$p1) return null;

        // minimal viability
        if (!$this->isPickViable($p1)) return null;

        $split = [
            ['ticker' => (string)($p1['ticker'] ?? ''), 'alloc_pct' => 1.0],
        ];

        $buyPlan = $this->buildBuyPlanForSplit($rows, $split, $capitalTotal, $reservePct);
        if ($capitalTotal !== null && !$this->isBuyPlanFeasible($buyPlan)) {
            return null;
        }

        $why = [
            'Fokus 1 posisi terbaik untuk eksekusi yang lebih disiplin.',
        ];

        $rankScore = $this->scoreStrategy($rows, $split, 1);

        return [
            'strategy_id' => 'S1',
            'label' => 'BUY_1_ONLY',
            'mode' => 'BUY_1',
            'positions' => 1,
            'split' => $split,
            'split_ratio' => '100',
            'entry_windows' => $entryWindows,
            'avoid_windows' => $avoidWindows,
            'entry_rule' => $entryRule,
            'rank_score' => $rankScore,
            'why' => $why,
            'risk_flags' => $this->collectRiskFlags($rows, $split),
            'buy_plan' => $buyPlan,
        ];
    }

    private function makeStrategyBuy2(array $rows, string $dow, ?float $capitalTotal, array $entryWindows, array $avoidWindows, string $entryRule, float $reservePct): ?array
    {
        $p1 = $rows[0] ?? null;
        $p2 = $rows[1] ?? null;
        if (!$p1 || !$p2) return null;

        // viability gating: if #2 weak, skip strategy buy2
        if (!$this->isPickViable($p1)) return null;
        if (!$this->isPickViable($p2, true)) return null;

        $s1 = (float)($p1['rank_score'] ?? $p1['watchlist_score'] ?? 0);
        $s2 = (float)($p2['rank_score'] ?? $p2['watchlist_score'] ?? 0);
        $gap = $s1 - $s2;

        $a = 0.6; $b = 0.4;
        if ($gap >= 12) { $a = 0.8; $b = 0.2; }
        elseif ($gap >= 6) { $a = 0.7; $b = 0.3; }
        elseif (abs($gap) <= 3) { $a = 0.5; $b = 0.5; }

        $split = [
            ['ticker' => (string)($p1['ticker'] ?? ''), 'alloc_pct' => (float)$a],
            ['ticker' => (string)($p2['ticker'] ?? ''), 'alloc_pct' => (float)$b],
        ];

        $buyPlan = $this->buildBuyPlanForSplit($rows, $split, $capitalTotal, $reservePct);
        if ($capitalTotal !== null && !$this->isBuyPlanFeasible($buyPlan)) {
            // if capital too small for 2 tickers, strategy buy2 not feasible
            return null;
        }

        $why = [
            'Bagi modal ke 2 posisi untuk diversifikasi risk.',
            'Prioritas lebih besar ke pick dengan skor lebih tinggi.',
        ];

        $rankScore = $this->scoreStrategy($rows, $split, 2);

        return [
            'strategy_id' => 'S2',
            'label' => 'BUY_2_SPLIT',
            'mode' => 'BUY_2_SPLIT',
            'positions' => 2,
            'split' => $split,
            'split_ratio' => (int)round($a*100) . ':' . (int)round($b*100),
            'entry_windows' => $entryWindows,
            'avoid_windows' => $avoidWindows,
            'entry_rule' => $entryRule,
            'rank_score' => $rankScore,
            'why' => $why,
            'risk_flags' => $this->collectRiskFlags($rows, $split),
            'buy_plan' => $buyPlan,
        ];
    }

    private function makeStrategyBuy3(array $rows, string $dow, ?float $capitalTotal, array $entryWindows, array $avoidWindows, string $entryRule, float $reservePct): ?array
    {
        $p1 = $rows[0] ?? null;
        $p2 = $rows[1] ?? null;
        $p3 = $rows[2] ?? null;
        if (!$p1 || !$p2 || !$p3) return null;

        // Viability gating: #3 must be reasonably strong.
        if (!$this->isPickViable($p1)) return null;
        if (!$this->isPickViable($p2, true)) return null;
        if (!$this->isPickViable($p3, true)) return null;

        $s1 = (float)($p1['rank_score'] ?? $p1['watchlist_score'] ?? 0);
        $s2 = (float)($p2['rank_score'] ?? $p2['watchlist_score'] ?? 0);
        $s3 = (float)($p3['rank_score'] ?? $p3['watchlist_score'] ?? 0);

        // Basic rule: only allow BUY_3 when #3 isn't too weak and score distance isn't too wide.
        $gap23 = $s2 - $s3;
        if ($s3 < 50 || $gap23 > 10) {
            return null;
        }

        // split ratio: default 50/30/20, adjust when top1 dominates
        $a = 0.5; $b = 0.3; $c = 0.2;
        if (($s1 - $s2) >= 10) { $a = 0.6; $b = 0.25; $c = 0.15; }
        if (($s2 - $s3) <= 2) { $b = 0.3; $c = 0.2; } // keep default

        $split = [
            ['ticker' => (string)($p1['ticker'] ?? ''), 'alloc_pct' => (float)$a],
            ['ticker' => (string)($p2['ticker'] ?? ''), 'alloc_pct' => (float)$b],
            ['ticker' => (string)($p3['ticker'] ?? ''), 'alloc_pct' => (float)$c],
        ];

        $buyPlan = $this->buildBuyPlanForSplit($rows, $split, $capitalTotal, $reservePct);
        if ($capitalTotal !== null && !$this->isBuyPlanFeasible($buyPlan)) {
            // capital too small for 3 tickers -> not feasible
            return null;
        }

        $why = [
            '3 posisi hanya saat semua top picks cukup layak & skor berdekatan.',
            'Diversifikasi risk tanpa mengorbankan kualitas pick.',
        ];

        $rankScore = $this->scoreStrategy($rows, $split, 3);

        return [
            'strategy_id' => 'S3',
            'label' => 'BUY_3_SPLIT',
            'mode' => 'BUY_3_SPLIT',
            'positions' => 3,
            'split' => $split,
            'split_ratio' => (int)round($a*100) . ':' . (int)round($b*100) . ':' . (int)round($c*100),
            'entry_windows' => $entryWindows,
            'avoid_windows' => $avoidWindows,
            'entry_rule' => $entryRule,
            'rank_score' => $rankScore,
            'why' => $why,
            'risk_flags' => $this->collectRiskFlags($rows, $split),
            'buy_plan' => $buyPlan,
        ];
    }

    /**
     * Basic viability check for a pick.
     *
     * @param bool $allowMedium if true, allow Medium confidence; otherwise require not Low.
     */
    private function isPickViable(array $row, bool $allowMedium = true): bool
    {
        if (!empty($row['is_expired'])) return false;
        $score = (float)($row['watchlist_score'] ?? $row['rank_score'] ?? 0);
        if ($score < 45) return false;

        $conf = strtoupper((string)($row['confidence'] ?? ''));
        if ($conf === 'LOW') return false;
        if (!$allowMedium && $conf !== 'HIGH') return false;

        // Exclude plan invalids from strategies (errors present)
        $tp = $row['trade_plan'] ?? null;
        if (is_array($tp) && !empty($tp['errors']) && is_array($tp['errors'])) {
            return false;
        }

        return true;
    }

    /**
     * Build buy_plan for a given split (used by each strategy).
     * Returns template if capital is null.
     */
    private function buildBuyPlanForSplit(array $topPicksRows, array $split, ?float $capitalTotal, float $reservePct): array
    {
        if (empty($split)) return [];

        if ($capitalTotal !== null && $capitalTotal > 0) {
            $usable = $capitalTotal * (1.0 - $reservePct);
            $remaining = $capitalTotal;
            $plan = [];

            foreach ($split as $s) {
                $ticker = (string)($s['ticker'] ?? '');
                $pct = (float)($s['alloc_pct'] ?? 0);
                $budget = $usable * $pct;

                $row = $this->findRowByTicker($topPicksRows, $ticker);
                $entry = $this->resolveEntryPrice($row);

                $lots = null;
                $est = null;
                if ($entry !== null && $entry > 0) {
                    $lotCost = $entry * self::LOT_SIZE;
                    $lots = (int) floor($budget / $lotCost);
                    if ($lots < 0) $lots = 0;
                    $est = $lots * $lotCost;
                    $remaining -= $est;
                }

                $exec = $this->buildExecutionSlices($row, $entry, $budget, $lots);

                $plan[] = [
                    'ticker' => $ticker,
                    'alloc_pct' => $pct,
                    'budget' => $budget,
                    'entry_price' => $entry,
                    'lot_size' => self::LOT_SIZE,
                    'lots' => $lots,
                    'estimated_cost' => $est,
                    'remaining_cash' => $remaining,

                    'execution_style' => $exec['execution_style'],
                    'entry_slices' => $exec['entry_slices'],
                    'slice_pcts' => $exec['slice_pcts'],
                    'slices' => $exec['slices'],
                ];
            }

            return $plan;
        }

        // template (no capital)
        $plan = [];
        foreach ($split as $s) {
            $ticker = (string)($s['ticker'] ?? '');
            $pct = (float)($s['alloc_pct'] ?? 0);
            $row = $this->findRowByTicker($topPicksRows, $ticker);
            $entry = $this->resolveEntryPrice($row);
            $exec = $this->buildExecutionSlices($row, $entry, null, null);

            $plan[] = [
                'ticker' => $ticker,
                'alloc_pct' => $pct,
                'budget' => null,
                'entry_price' => $entry,
                'lot_size' => self::LOT_SIZE,
                'lots' => null,
                'estimated_cost' => null,
                'remaining_cash' => null,

                'execution_style' => $exec['execution_style'],
                'entry_slices' => $exec['entry_slices'],
                'slice_pcts' => $exec['slice_pcts'],
                'slices' => $exec['slices'],
            ];
        }
        return $plan;
    }

    private function resolveEntryPrice(?array $row): ?float
    {
        if (!$row) return null;
        $tp = $row['trade_plan'] ?? null;
        if (is_array($tp) && isset($tp['entry']) && is_numeric($tp['entry'])) {
            $v = (float)$tp['entry'];
            if ($v > 0) return $v;
        }
        if (isset($row['close']) && is_numeric($row['close'])) {
            $v = (float)$row['close'];
            if ($v > 0) return $v;
        }
        return null;
    }

    /**
     * Feasible if every planned ticker has at least 1 lot.
     */
    private function isBuyPlanFeasible(array $buyPlan): bool
    {
        if (empty($buyPlan)) return false;
        foreach ($buyPlan as $p) {
            if (!is_array($p)) return false;
            if (!isset($p['lots'])) return false;
            if ((int)$p['lots'] < 1) return false;
        }
        return true;
    }

    /**
     * Strategy score heuristic (deterministic): weighted avg score - penalties.
     */
    private function scoreStrategy(array $topPicksRows, array $split, int $positions): float
    {
        $score = 0.0;
        $riskPenalty = 0.0;
        $confPenalty = 0.0;
        foreach ($split as $s) {
            $t = (string)($s['ticker'] ?? '');
            $pct = (float)($s['alloc_pct'] ?? 0);
            $row = $this->findRowByTicker($topPicksRows, $t);
            if (!$row) continue;
            $sc = (float)($row['watchlist_score'] ?? $row['rank_score'] ?? 0);
            $score += $sc * $pct;

            $conf = strtoupper((string)($row['confidence'] ?? ''));
            if ($conf === 'MEDIUM') $confPenalty += 2.0;
            elseif ($conf === 'LOW') $confPenalty += 6.0;

            $gap = isset($row['gap_pct']) && is_numeric($row['gap_pct']) ? abs((float)$row['gap_pct']) : 0.0;
            $atr = isset($row['atr_pct']) && is_numeric($row['atr_pct']) ? (float)$row['atr_pct'] : 0.0;
            $vr  = isset($row['vol_ratio']) && is_numeric($row['vol_ratio']) ? (float)$row['vol_ratio'] : 0.0;
            if ($gap >= 3.0) $riskPenalty += 2.0;
            if ($atr >= 6.0) $riskPenalty += 2.0;
            if ($vr >= 2.5) $riskPenalty += 1.0;
        }

        // complexity penalty: more positions => harder execution
        $complexityPenalty = max(0, $positions - 1) * 1.5;

        $final = $score - $riskPenalty - $confPenalty - $complexityPenalty;
        if ($final < 0) $final = 0.0;
        return round($final, 2);
    }

    private function collectRiskFlags(array $topPicksRows, array $split): array
    {
        $flags = [];
        foreach ($split as $s) {
            $t = (string)($s['ticker'] ?? '');
            $row = $this->findRowByTicker($topPicksRows, $t);
            if (!$row) continue;
            $gap = isset($row['gap_pct']) && is_numeric($row['gap_pct']) ? abs((float)$row['gap_pct']) : null;
            $atr = isset($row['atr_pct']) && is_numeric($row['atr_pct']) ? (float)$row['atr_pct'] : null;
            $vr  = isset($row['vol_ratio']) && is_numeric($row['vol_ratio']) ? (float)$row['vol_ratio'] : null;
            if ($gap !== null && $gap >= 3.0) $flags[] = 'GAP_RISK_HIGH:' . strtoupper($t);
            if ($atr !== null && $atr >= 6.0) $flags[] = 'VOLATILITY_HIGH:' . strtoupper($t);
            if ($vr !== null && $vr >= 2.5) $flags[] = 'VOL_SPIKE:' . strtoupper($t);
        }
        return array_values(array_unique($flags));
    }

    /**
     * Build execution breakdown per ticker.
     *
     * buy_plan lots itu total alokasi (100% budget per ticker). Eksekusinya dipecah jadi beberapa slice.
     * Ini membuat UI bisa menampilkan: beli sekali atau bertahap (per ticker), dengan aturan yang jelas.
     *
     * @param array|null $row
     * @param float|null $entryPrice
     * @param float|null $budget
     * @param int|null $lots
     * @return array{execution_style:string,entry_slices:int,slice_pcts:array<int,float>,slices:array<int,array>}
     */
    private function buildExecutionSlices(?array $row, ?float $entryPrice, ?float $budget, ?int $lots): array
    {
        $decision = $this->decideExecutionStyle($row);
        $pcts = $decision['slice_pcts'];

        $slices = [];
        $lotsParts = $this->splitLots($lots, $pcts);

        $n = count($pcts);
        for ($i = 0; $i < $n; $i++) {
            $sliceLots = $lotsParts[$i] ?? null;
            $sliceBudget = null;
            if ($budget !== null && $budget > 0) {
                $sliceBudget = $budget * $pcts[$i];
            }

            $slices[] = [
                'slice' => $i + 1,
                'pct' => $pcts[$i],
                'lots' => $sliceLots,
                'budget' => $sliceBudget,
                'entry_price' => $entryPrice,
                'when' => $decision['when'][$i] ?? '',
            ];
        }

        return [
            'execution_style' => $decision['execution_style'],
            'entry_slices' => (int)$decision['entry_slices'],
            'slice_pcts' => $pcts,
            'slices' => $slices,
        ];
    }

    /**
     * Deterministic rule to decide per-ticker entry style.
     *
     * Heuristic:
     * - ONE_SHOT only if confidence high AND low volatility/gap risk.
     * - SPLIT_3 if risk high or setup Reversal.
     * - otherwise SPLIT_2 (default).
     */
    private function decideExecutionStyle(?array $row): array
    {
        $confidence = strtoupper((string)($row['confidence'] ?? ''));
        $setupType = (string)($row['setup_type'] ?? '');

        $gapPct = null;
        if (isset($row['gap_pct']) && is_numeric($row['gap_pct'])) {
            $gapPct = abs((float)$row['gap_pct']);
        }

        $atrPct = null;
        if (isset($row['atr_pct']) && is_numeric($row['atr_pct'])) {
            $atrPct = (float)$row['atr_pct'];
        }

        $volRatio = null;
        if (isset($row['vol_ratio']) && is_numeric($row['vol_ratio'])) {
            $volRatio = (float)$row['vol_ratio'];
        }

        $gapHigh = ($gapPct !== null && $gapPct >= 3.0);
        $volHigh = ($atrPct !== null && $atrPct >= 6.0);
        $volSpike = ($volRatio !== null && $volRatio >= 2.5);

        // ONE_SHOT when conditions are clean
        $clean = (!$gapHigh) && (!$volHigh) && (!$volSpike);
        if ($confidence === 'HIGH' && $clean && $setupType !== 'Reversal') {
            return [
                'execution_style' => 'ONE_SHOT',
                'entry_slices' => 1,
                'slice_pcts' => [1.0],
                'when' => [
                    'Entry sekali di window pertama saat spread normal; jangan mengejar (no chasing).',
                ],
            ];
        }

        // SPLIT_3 on higher risk / reversal
        if ($gapHigh || $volHigh || $setupType === 'Reversal') {
            return [
                'execution_style' => 'SPLIT_3',
                'entry_slices' => 3,
                'slice_pcts' => self::SLICE_PCTS_3,
                'when' => [
                    'Slice 1: entry kecil di area plan (uji likuiditas/spread).',
                    'Slice 2: tambah saat konfirmasi (hold level / higher low).',
                    'Slice 3: tambah terakhir hanya jika breakout/continuation valid (bukan spike).',
                ],
            ];
        }

        // default
        $setupHint = 'konfirmasi (hold/break)';
        if ($setupType === 'Breakout') $setupHint = 'break & hold di atas resistance';
        elseif ($setupType === 'Pullback') $setupHint = 'bounce/rejection di MA/support';

        return [
            'execution_style' => 'SPLIT_2',
            'entry_slices' => 2,
            'slice_pcts' => self::SLICE_PCTS_2,
            'when' => [
                'Slice 1: entry utama di window pertama saat spread normal.',
                'Slice 2: tambah hanya jika ada ' . $setupHint . ' (jangan FOMO).',
            ],
        ];
    }

    /**
     * Split lots into integer parts, preserving total.
     * Remainder is added to the first slice to keep deterministic.
     *
     * @param int|null $lots
     * @param array<int,float> $pcts
     * @return array<int,int|null>
     */
    private function splitLots(?int $lots, array $pcts): array
    {
        $n = count($pcts);
        if ($n <= 0) return [];
        if ($lots === null) {
            // template mode (no capital provided)
            return array_fill(0, $n, null);
        }
        if ($lots <= 0) {
            return array_fill(0, $n, 0);
        }

        $parts = [];
        $allocated = 0;
        for ($i = 0; $i < $n; $i++) {
            $p = (float)$pcts[$i];
            if ($p < 0) $p = 0;
            $x = (int) floor($lots * $p);
            if ($x < 0) $x = 0;
            $parts[$i] = $x;
            $allocated += $x;
        }

        $rem = $lots - $allocated;
        if ($rem > 0) {
            $parts[0] += $rem;
        }

        return $parts;
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
